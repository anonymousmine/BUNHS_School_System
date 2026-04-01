<?php
include 'db_connection.php';
include 'enhanced_functions.php';

// ============================================================
//  DYNAMIC EVENT LOADING WITH AJAX
// ============================================================

// Function to get all events (enhanced version)
function get_all_events($conn)
{
    return getAllEventsEnhanced();
}

// Function to get upcoming events (from today onwards, including events still ongoing)
function get_upcoming_events($conn, $limit = 10)
{
    $today = date("Y-m-d");
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based, location, image, organizer_name, organizer_position, organizer_contact, source, is_official FROM events WHERE event_date >= ? OR DATE_ADD(event_date, INTERVAL (COALESCE(event_days, 1) - 1) DAY) >= ? ORDER BY event_date ASC LIMIT ?");
    $stmt->bind_param("ssi", $today, $today, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

// Function to get events for a specific month
function get_events_by_month($conn, $year, $month)
{
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based, location, image, organizer_name, organizer_position, organizer_contact, source, is_official FROM events WHERE YEAR(event_date) = ? AND MONTH(event_date) = ? ORDER BY event_date ASC");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();
    return $events;
}

// Function to get event counts by category
function get_category_counts($conn)
{
    $categories = ['Academic', 'Sports', 'Cultural', 'Workshops', 'Conferences', 'Academic Calendar', 'Holidays', 'Health & Nutrition', 'Governance & Elections', 'Assessments', 'Professional Development', 'Remedial & Intervention'];
    $today = date("Y-m-d");
    $counts = [];
    foreach ($categories as $category) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE category = ? AND (event_date >= ? OR DATE_ADD(event_date, INTERVAL (COALESCE(event_days, 1) - 1) DAY) >= ?)");
        $stmt->bind_param("sss", $category, $today, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $counts[$category] = $row['count'];
        $stmt->close();
    }
    return $counts;
}

// Function to get filtered events
function get_filtered_events($conn, $category_filter = null, $official_only = false)
{
    $sql = "SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based, source, is_official FROM events WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($category_filter) {
        $sql .= " AND category = ?";
        $params[] = $category_filter;
        $types .= "s";
    }
    
    if ($official_only) {
        $sql .= " AND is_official = 1";
    }
    
    $sql .= " ORDER BY event_date ASC";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    if (isset($stmt)) {
        $stmt->close();
    }
    
    return $events;
}

// Function to get featured event
function get_featured_event($conn)
{
    $today = date("Y-m-d");
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events WHERE event_date = ? ORDER BY event_date ASC LIMIT 1");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $stmt->close();
        return $event;
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events WHERE event_date > ? ORDER BY event_date ASC LIMIT 1");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $stmt->close();
        return $event;
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT id, title, description, event_date, category, event_start_time, event_end_time, event_days, team_based FROM events ORDER BY event_date DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $stmt->close();
        return $event;
    }
    $stmt->close();
    return null;
}

// Handle filtering
$category_filter = isset($_GET['category']) ? $_GET['category'] : null;
$official_only = isset($_GET['official']) && $_GET['official'] === '1';

$upcoming_events = get_filtered_events($conn, $category_filter, $official_only);
$category_counts = get_category_counts($conn);
$featured_event = get_featured_event($conn);
$today = date("Y-m-d");
$is_current_event = false;

// Get count of official events
$official_events_count = 0;
if (!empty($upcoming_events)) {
    foreach ($upcoming_events as $event) {
        if (isset($event['is_official']) && $event['is_official'] == 1) {
            $official_events_count++;
        }
    }
}
if ($featured_event && $featured_event['event_date'] == $today) {
    $is_current_event = true;
}

$currentMonth = date('n');
$currentYear = date('Y');
$monthEvents = get_events_by_month($conn, $currentYear, $currentMonth);
$eventsData = [];
foreach ($monthEvents as $event) {
    $dateKey = $event['event_date'];
    if (!isset($eventsData[$dateKey])) {
        $eventsData[$dateKey] = [];
    }
    $eventsData[$dateKey][] = $event;
}
$eventsDataJson = json_encode($eventsData);

// ============================================================
//  AJAX HANDLER INTEGRATION
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    include 'ajax_handlers.php';
    exit;
}

// AJAX Handler for getting all events (legacy support)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_all_events':
            $all_events = get_all_events($conn);
            echo json_encode(['status' => 'success', 'events' => $all_events]);
            exit;
            
        case 'get_events':
            $year = intval($_POST['year'] ?? date('Y'));
            $month = intval($_POST['month'] ?? date('n'));
            $events = get_events_by_month($conn, $year, $month);
            echo json_encode(['status' => 'success', 'events' => $events]);
            exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Events - Buyoan National High School</title>
    <meta name="description" content="">
    <meta name="keywords" content="">

    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>

    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Raleway:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet"> -->
    <!-- <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet"> -->

    <link href="assets/css/main.css" rel="stylesheet">

    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">

    <style>
        /* ── Core Variables ───────────────────────────────────── */
        :root {
            --moss: #4a7c59;
            --moss-dark: #355c42;
            --moss-light: #6a9c79;
            --moss-xlight: #e8f0ea;
            --moss-pale: #f2f7f3;
            --white: #ffffff;
            --ink: #1e2a22;
            --ink-light: #4a5a50;
            --ink-muted: #8a9e90;
            --border: rgba(74, 124, 89, 0.15);
            --shadow-sm: 0 2px 8px rgba(74, 124, 89, 0.08);
            --shadow-md: 0 6px 24px rgba(74, 124, 89, 0.12);
            --shadow-lg: 0 16px 48px rgba(74, 124, 89, 0.16);
            --radius-sm: 8px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-xl: 28px;
        }

        /* ── Base ─────────────────────────────────────────────── */
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--white);
            color: var(--ink);
        }

        /* ── Section Layout ───────────────────────────────────── */
        #events-2 {
            padding: 60px 0 80px;
            background: var(--white);
        }

        /* ── Section Header ───────────────────────────────────── */
        .events-section-header {
            margin-bottom: 40px;
        }

        .events-section-header .section-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: var(--moss);
            background: var(--moss-xlight);
            padding: 6px 14px;
            border-radius: 30px;
            margin-bottom: 12px;
        }

        .events-section-header .section-label::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--moss);
        }

        .events-section-header h2 {
            font-family: 'DM Serif Display', serif;
            font-size: 2rem;
            color: var(--ink);
            margin: 0;
            line-height: 1.2;
        }

        .events-section-header .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .events-section-header .header-main {
            flex: 1;
        }

        .events-section-header .export-controls {
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .events-section-header .header-content {
                flex-direction: column;
                gap: 16px;
            }

            .events-section-header .export-controls {
                align-self: flex-start;
            }
        }

        .events-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-muted);
            margin-top: 8px;
        }

        /* ── Event Cards ──────────────────────────────────────── */
        .events-list {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 18px;
        }

        .events-list .event-item {
            display: flex;
            flex-direction: column;
            gap: 0;
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 1.5px solid var(--border);
            overflow: hidden;
            transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            flex: 1 1 calc(50% - 9px);
            min-width: 260px;
        }

        .events-list .event-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            height: 3px;
            background: var(--moss);
            border-radius: 4px 4px 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .events-list .event-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(74, 124, 89, 0.3);
        }

        .events-list .event-item:hover::before {
            opacity: 1;
        }

        /* Date block */
        .events-list .event-item .event-date {
            flex-shrink: 0;
            width: 100%;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: var(--moss-pale);
            border-bottom: 1.5px solid var(--border);
            transition: background 0.3s ease;
        }

        .events-list .event-item:hover .event-date {
            background: var(--moss);
        }

        .events-list .event-item .event-date .day {
            display: block;
            font-family: 'DM Serif Display', serif;
            font-size: 32px;
            font-weight: 400;
            line-height: 1;
            color: var(--moss-dark);
            transition: color 0.3s ease;
        }

        .events-list .event-item:hover .event-date .day {
            color: var(--white);
        }

        .events-list .event-item .event-date .month {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--moss);
            padding: 0;
            background: transparent;
            margin-top: 2px;
            transition: color 0.3s ease;
        }

        .events-list .event-item:hover .event-date .month {
            color: rgba(255, 255, 255, 0.8);
        }

        /* Content block */
        .events-list .event-item .event-content {
            flex: 1;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
        }

        .events-list .event-item .event-content h3 {
            font-family: 'DM Serif Display', serif;
            font-size: 1.15rem;
            font-weight: 400;
            color: var(--ink);
            margin: 0;
            line-height: 1.3;
        }

        /* Category badge */
        .event-category-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            width: fit-content;
        }

        .event-category-tag.academic {
            background: #dbeafe;
            color: #1e40af;
        }

        .event-category-tag.sports {
            background: #fee2e2;
            color: #991b1b;
        }

        .event-category-tag.cultural {
            background: #ede9fe;
            color: #5b21b6;
        }

        .event-category-tag.workshops {
            background: #fef3c7;
            color: #92400e;
        }

        .event-category-tag.conferences {
            background: var(--moss-xlight);
            color: var(--moss-dark);
        }

        .events-list .event-item .event-meta {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
        }

        .events-list .event-item .event-meta p {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: var(--ink-muted);
            margin: 0;
            font-weight: 500;
        }

        .events-list .event-item .event-meta i {
            color: var(--moss);
            font-size: 13px;
        }

        .events-list .event-item .event-content>p {
            font-size: 13.5px;
            color: var(--ink-light);
            margin: 0;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* CTA button */
        .events-list .event-item .btn-event {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--moss-dark);
            font-weight: 700;
            font-size: 12.5px;
            text-decoration: none;
            transition: all 0.25s ease;
            margin-top: 4px;
            width: fit-content;
            padding: 6px 14px;
            border-radius: 30px;
            background: var(--moss-xlight);
            border: 1.5px solid var(--border);
        }

        .events-list .event-item .btn-event:hover {
            background: var(--moss);
            color: var(--white);
            border-color: var(--moss);
            gap: 10px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 64px 24px;
            background: var(--moss-pale);
            border-radius: var(--radius-xl);
            border: 2px dashed var(--border);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--moss-light);
            opacity: 0.5;
            margin-bottom: 16px;
        }

        .empty-state p {
            color: var(--ink-muted);
            font-size: 15px;
            margin: 0;
        }

        /* ── Events Grid Layout ───────────────────────────────── */
        .events-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (min-width: 768px) {
            .events-list {
                grid-template-columns: 1fr 1fr;
            }
        }

        .events-grid-left, .events-grid-right {
            display: flex;
            flex-direction: column;
            gap: 24px;
            align-items: stretch; /* Make all cards same width */
        }

        /* Ensure equal height for event cards in grid */
        .events-list .event-card {
            height: 100%;
            min-height: 320px; /* Increased minimum height for better content fit */
            width: 100%; /* Ensure full width */
            flex: 1; /* Allow cards to grow and shrink */
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Prevent content from overflowing */
            box-sizing: border-box; /* Include padding in height calculation */
        }

        /* Ensure consistent alignment in responsive layout */
        @media (max-width: 767px) {
            .events-list {
                grid-template-columns: 1fr;
            }
            
            .events-list .event-card {
                min-height: 300px; /* Increased mobile height for better content fit */
            }
        }

        /* ── Full Width Layout ─────────────────────────────────── */
        .events-2 .container-fluid {
            padding-left: 20px;
            padding-right: 20px;
        }

        .events-2 .col-lg-7 {
            display: flex;
            flex-direction: column;
        }

        .events-list-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* ── Sidebar ──────────────────────────────────────────── */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .sidebar-item {
            background: var(--white);
            border-radius: var(--radius-xl);
            border: 1.5px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .sidebar-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border-color: var(--moss);
        }

        .sidebar-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--moss) 0%, #16a085 50%, #1abc9c 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-item:hover::before {
            opacity: 1;
        }

        .sidebar-item-header {
            padding: 20px 24px;
            border-bottom: 1.5px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, var(--moss-pale) 0%, rgba(22, 163, 74, 0.05) 100%);
            position: relative;
            overflow: hidden;
        }

        .sidebar-item-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--moss) 0%, #16a085 50%, #1abc9c 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .sidebar-item:hover .sidebar-item-header::after {
            transform: scaleX(1);
        }

        .sidebar-item-header .header-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--moss) 0%, #16a085 100%);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.2);
            transition: all 0.3s ease;
        }

        .sidebar-item:hover .header-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .sidebar-item-header h3 {
            font-family: 'DM Serif Display', serif;
            font-size: 1.1rem;
            color: var(--ink);
            margin: 0;
            font-weight: 600;
            letter-spacing: -0.01em;
            transition: all 0.3s ease;
        }

        .sidebar-item:hover .sidebar-item-header h3 {
            color: var(--moss);
            transform: translateX(2px);
        }

        /* ── Calendar ─────────────────────────────────────────── */
        .calendar-container {
            max-width: 100%;
        }

        .calendar-wrapper {
            border-radius: 0;
            overflow: hidden;
        }

        .month {
            padding: 18px 20px;
            width: 100%;
            background: var(--moss);
            text-align: center;
        }

        .month ul {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .month ul li {
            color: white;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .month .prev,
        .month .next {
            cursor: pointer;
            color: white;
            transition: all 0.3s ease;
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 18px;
            background: rgba(255, 255, 255, 0.1);
        }

        .month .prev:hover,
        .month .next:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.1);
        }

        .weekdays {
            margin: 0;
            padding: 10px 8px 6px;
            background: var(--moss-pale);
            display: flex;
            border-bottom: 1.5px solid var(--border);
        }

        .weekdays li {
            display: inline-block;
            width: 14.28%;
            color: var(--moss-dark);
            text-align: center;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .days {
            padding: 8px;
            background: var(--white);
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            list-style: none;
        }

        .days li {
            list-style-type: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 14.28%;
            text-align: center;
            margin-bottom: 2px;
            font-size: 13px;
            color: var(--ink-light);
            padding: 10px 0;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: var(--radius-sm);
            min-height: 42px;
            font-weight: 500;
        }

        .days li:hover:not(.other-month):not(.has-event) {
            background: var(--moss);
            color: white;
        }

        .days li.other-month {
            color: #cdd8cf;
            font-weight: 400;
        }

        .days li.other-month:hover:not(.has-event) {
            background: var(--moss-xlight);
            color: var(--ink-muted);
        }

        .days li.today {
            font-weight: 800;
            color: var(--white);
            background: var(--moss-dark);
            border-radius: var(--radius-sm);
        }

        .days li.today::after {
            display: none;
        }

        .days li .event-dot {
            position: absolute;
            bottom: 5px;
            left: 50%;
            transform: translateX(-50%);
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: var(--moss);
        }

        .days li.today .event-dot {
            background: rgba(255, 255, 255, 0.7);
        }

        .days li .event-dot.academic {
            background: #3b82f6;
        }

        .days li .event-dot.sports {
            background: #ef4444;
        }

        .days li .event-dot.cultural {
            background: #8b5cf6;
        }

        .days li .event-dot.workshops {
            background: #f59e0b;
        }

        .days li .event-dot.conferences {
            background: var(--moss);
        }

        .days li .event-dot.academic-calendar {
            background: #059669;
        }

        .days li .event-dot.holidays {
            background: #dc2626;
        }

        .days li .event-dot.health-\&-nutrition {
            background: #16a34a;
        }

        .days li .event-dot.governance-\&-elections {
            background: #7c3aed;
        }

        .days li .event-dot.assessments {
            background: #ea580c;
        }

        .days li .event-dot.professional-development {
            background: #0891b2;
        }

        .days li .event-dot.remedial-\&-intervention {
            background: #ca8a04;
        }

        .days li .event-dot.official-deped-events-only {
            background: #1e40af;
        }

        /* ── Featured Event ───────────────────────────────────── */
        .featured-event-content {
            padding: 20px;
        }

        .featured-event-content h4 {
            font-family: 'DM Serif Display', serif;
            font-size: 1.05rem;
            color: var(--ink);
            margin: 0 0 10px;
            line-height: 1.35;
        }

        .featured-event-content p {
            font-size: 13px;
            color: var(--ink-muted);
            margin: 0 0 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .featured-event-content p i {
            color: var(--moss);
        }

        .featured-event-content .featured-desc {
            font-size: 13px;
            color: var(--ink-light);
            line-height: 1.6;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1.5px solid var(--border);
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* ── Event Categories ─────────────────────────────────── */
        .categories ul {
            list-style: none;
            padding: 16px 20px;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .categories ul li a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--ink-light);
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1.5px solid transparent;
        }

        .categories ul li a:hover {
            background: var(--moss-pale);
            color: var(--moss-dark);
            border-color: var(--border);
            padding-left: 18px;
        }

        .categories ul li a::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .categories ul li:nth-child(1) a::before {
            background: #3b82f6;
        }

        .categories ul li:nth-child(2) a::before {
            background: #ef4444;
        }

        .categories ul li:nth-child(3) a::before {
            background: #8b5cf6;
        }

        .categories ul li:nth-child(4) a::before {
            background: #f59e0b;
        }

        .categories ul li:nth-child(5) a::before {
            background: var(--moss);
        }

        .categories ul li a span {
            font-size: 11px;
            font-weight: 700;
            color: var(--white);
            background: var(--moss);
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: auto;
        }

        /* ── Modal ────────────────────────────────────────────── */
        .event-modal .modal-content {
            border: none;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .event-modal .event-date-header {
            background: linear-gradient(135deg, var(--moss) 0%, #16a085 50%, #1abc9c 100%);
            color: white;
            padding: 32px 40px;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .event-modal .event-date-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 0;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .event-modal .event-date-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 60%);
            border-radius: 50%;
            z-index: 0;
            animation: float-reverse 8s ease-in-out infinite;
        }

        @keyframes float-reverse {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(-180deg); }
        }

        .event-modal .event-date-header > * {
            position: relative;
            z-index: 1;
        }

        .event-modal .event-date-header .header-content {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            text-align: center;
        }

        .event-modal .event-date-header h5 {
            font-family: 'DM Serif Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .event-modal .event-date-display {
            font-size: 1.1rem;
            font-weight: 500;
            opacity: 0.95;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 16px;
            border-radius: 50px;
            display: inline-block;
            backdrop-filter: blur(10px);
        }

        .event-modal #eventDateDisplay {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .event-modal #eventDateDisplay::before {
            content: '📅';
            font-size: 16px;
        }

        .event-modal .event-date-header .header-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(5px);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }

        .event-modal .event-date-header .header-icon i {
            font-size: 1.4rem;
            color: white;
            animation: rotate 4s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .events-list-modal {
            max-height: 340px;
            overflow-y: auto;
            padding: 4px 2px;
        }

        .school-days-info {
            padding: 16px 20px;
        }

        .school-days-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .school-days-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--moss-green-primary);
            font-family: 'DM Serif Display', serif;
        }

        .school-days-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .school-days-description {
            text-align: center;
        }

        /* ── Advanced Search & Filter Bar ─────────────────────────────────── */
        .events-search-filter-bar {
            background: var(--moss-pale);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }

        .search-container {
            flex: 1;
            min-width: 280px;
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            color: var(--text-muted);
            font-size: 16px;
            z-index: 1;
        }

        .search-input {
            width: 100%;
            padding: 10px 40px 10px 36px;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 14px;
            background: white;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--moss);
            box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.1);
        }

        .search-clear-btn {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .search-clear-btn:hover {
            background: var(--border);
            color: var(--text-primary);
        }

        .filter-controls {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .date-filter,
        .sort-filter {
            min-width: 140px;
        }

        .form-select-sm {
            font-size: 13px;
            padding: 6px 8px;
            border-radius: var(--radius-sm);
        }

        /* ── Advanced Filters Panel ─────────────────────────────────── */
        .advanced-filters-panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .filter-label {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 4px;
        }

        .date-range-inputs {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-range-inputs input {
            flex: 1;
        }

        .date-separator {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
        }

        .category-checkboxes,
        .event-type-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .form-check-inline {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }

        .form-check-label {
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .form-check-input:checked + .form-check-label {
            color: var(--moss);
            font-weight: 500;
        }

        .filters-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        /* ── Enhanced Event Cards ───────────────────────────────────────── */
        .event-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            height: 100%; /* Make all cards same height */
            display: flex;
            flex-direction: column;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--moss);
        }

        .event-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
            flex-shrink: 0; /* Prevent header from shrinking */
        }

        .event-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto; /* Push footer to bottom */
            padding-top: 16px;
            flex-shrink: 0; /* Prevent footer from shrinking */
        }

        .event-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.4;
            overflow: hidden; /* Prevent title overflow */
            text-overflow: ellipsis; /* Add ellipsis for long titles */
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Limit to 2 lines */
            -webkit-box-orient: vertical;
            word-wrap: break-word; /* Break long words */
        }

        .event-card-badges {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-end;
        }

        .official-badge {
            background: linear-gradient(135deg, var(--moss), #16a085);
            color: white;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .event-category-badge {
            background: var(--moss-pale);
            color: var(--moss);
            padding: 4px 10px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 500;
        }

        .event-card-body {
            display: flex;
            gap: 20px;
            align-items: stretch; /* Make both sections same height */
            flex: 1; /* Allow body to take available space */
        }

        .event-card-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .event-card-description {
            flex: 1;
            margin-bottom: 12px;
            color: var(--ink-muted);
            font-size: 14px;
            line-height: 1.6;
            overflow: hidden; /* Prevent text overflow */
            text-overflow: ellipsis; /* Add ellipsis for long text */
            display: -webkit-box;
            -webkit-line-clamp: 3; /* Limit to 3 lines */
            -webkit-box-orient: vertical;
        }

        .event-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 12px;
            overflow: hidden; /* Prevent meta overflow */
        }

        .event-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--ink-muted);
            white-space: nowrap; /* Prevent text wrapping */
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .event-meta-item i {
            color: var(--moss);
        }

        .event-card-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            overflow: hidden; /* Prevent actions overflow */
        }

        .event-card-date {
            background: linear-gradient(135deg, var(--moss), #16a085);
            color: white;
            padding: 16px;
            border-radius: var(--radius-md);
            text-align: center;
            min-width: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%; /* Make date section same height as content */
        }

        .event-date-day {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
        }

        .event-date-month {
            font-size: 0.75rem;
            text-transform: uppercase;
            opacity: 0.9;
            margin-top: 2px;
        }

        /* ── Loading States & Empty States ─────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }

        .empty-state p {
            font-size: 14px;
            margin-bottom: 20px;
        }

        .skeleton-loader {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: var(--radius-md);
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ── Calendar View Controls ─────────────────────────────────── */
        .calendar-view-controls {
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
        }

        .view-mode-switcher {
            display: flex;
            gap: 4px;
            background: var(--moss-pale);
            padding: 4px;
            border-radius: var(--radius-md);
        }

        .view-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .view-btn:hover {
            background: rgba(255, 255, 255, 0.5);
            color: var(--text-primary);
        }

        .view-btn.active {
            background: var(--moss);
            color: white;
            box-shadow: 0 2px 4px rgba(26, 188, 156, 0.3);
        }

        .view-btn i {
            font-size: 14px;
        }

        /* ── Week View ─────────────────────────────────── */
        .calendar-week-view {
            display: none;
        }

        .calendar-week-view.active {
            display: block;
        }

        .week-grid {
            display: grid;
            grid-template-columns: 60px repeat(7, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .week-time-slot {
            background: white;
            padding: 8px 4px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .week-day-header {
            background: var(--moss-pale);
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            color: var(--moss);
            font-size: 12px;
            border-bottom: 1px solid var(--border);
        }

        .week-day-cell {
            background: white;
            min-height: 60px;
            padding: 4px;
            position: relative;
        }

        .week-event {
            background: var(--moss);
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-bottom: 2px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ── Day View ─────────────────────────────────── */
        .calendar-day-view {
            display: none;
        }

        .calendar-day-view.active {
            display: block;
        }

        .day-timeline {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .day-time-slot {
            display: flex;
            border-bottom: 1px solid var(--border);
            min-height: 40px;
        }

        .day-time-label {
            width: 60px;
            padding: 8px;
            background: var(--moss-pale);
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
            border-right: 1px solid var(--border);
        }

        .day-events-container {
            flex: 1;
            padding: 4px 8px;
            position: relative;
        }

        .day-event-block {
            background: var(--moss);
            color: white;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            margin-bottom: 4px;
            cursor: pointer;
            font-size: 12px;
            position: relative;
        }

        .day-event-time {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .day-event-title {
            opacity: 0.9;
        }

        /* ── Calendar Event Days ─────────────────────────────────── */
        #calendarDays li.has-event {
            background-color: #ff4757;
            color: #fff;
            border-radius: 0;
            aspect-ratio: 1 / 1;
            width: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2px auto;
            padding: 0;
            box-sizing: border-box;
            cursor: pointer;
            border: none;
            font-weight: 600;
        }

        #calendarDays li.has-event:hover {
            background-color: #ff3838;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }

        #calendarDays li.has-event.today {
            background-color: #ff4757;
            color: #fff;
            border: 2px solid #ff3838;
            font-weight: 700;
            box-shadow: 0 4px 16px rgba(255, 71, 87, 0.4);
        }

        /* Category-specific event day colors */
        #calendarDays li.has-event.academic { 
            background-color: #1abc9c;
            color: #fff;
        }
        #calendarDays li.has-event.academic:hover {
            background-color: #16a085;
            box-shadow: 0 4px 12px rgba(26, 188, 156, 0.3);
        }

        #calendarDays li.has-event.sports { 
            background-color: #e74c3c;
            color: #fff;
        }
        #calendarDays li.has-event.sports:hover {
            background-color: #c0392b;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        #calendarDays li.has-event.cultural { 
            background-color: #9b59b6;
            color: #fff;
        }
        #calendarDays li.has-event.cultural:hover {
            background-color: #8e44ad;
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
        }

        #calendarDays li.has-event.holidays { 
            background-color: #c0392b;
            color: #fff;
        }
        #calendarDays li.has-event.holidays:hover {
            background-color: #dc2626;
            box-shadow: 0 4px 12px rgba(192, 57, 43, 0.3);
        }

        #calendarDays li.has-event.multiple-events {
            background: linear-gradient(135deg, 
                #1abc9c 0%, 
                #e74c3c 50%, 
                #9b59b6 100%);
            color: #fff;
        }
        #calendarDays li.has-event.multiple-events:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
        }

        #calendarDays li.has-event.assessments { 
            background-color: #ea580c;
            color: #fff;
        }
        #calendarDays li.has-event.assessments:hover {
            background-color: #dc2626;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
        }

        #calendarDays li.has-event.professional-development { 
            background-color: #0891b2;
            color: #fff;
        }
        #calendarDays li.has-event.professional-development:hover {
            background-color: #0e7490;
            box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);
        }

        #calendarDays li.has-event.remedial-\&-intervention { 
            background-color: #ca8a04;
            color: #fff;
        }
        #calendarDays li.has-event.remedial-\&-intervention:hover {
            background-color: #a16207;
            box-shadow: 0 4px 12px rgba(202, 138, 4, 0.3);
        }

        #calendarDays li.has-event.workshops { 
            background-color: #f59e0b;
            color: #fff;
        }
        #calendarDays li.has-event.workshops:hover {
            background-color: #d97706;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        #calendarDays li.has-event.conferences { 
            background-color: var(--moss);
            color: #fff;
        }
        #calendarDays li.has-event.conferences:hover {
            background-color: #16a085;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        #calendarDays li.has-event.official-deped-events-only { 
            background-color: #1e40af;
            color: #fff;
        }
        #calendarDays li.has-event.official-deped-events-only:hover {
            background-color: #1e3a8a;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }

        
        /* ── Event Reminders System ─────────────────────────────────── */
        .reminders-container {
            padding: 16px 20px;
        }

        .upcoming-reminders {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 12px;
        }

        .reminder-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px;
            background: var(--moss-pale);
            border-radius: var(--radius-md);
            margin-bottom: 8px;
            border-left: 3px solid var(--moss);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .reminder-item:hover {
            background: var(--moss);
            color: white;
            transform: translateX(2px);
        }

        .reminder-item:hover .reminder-time,
        .reminder-item:hover .reminder-title {
            color: white;
        }

        .reminder-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            background: var(--moss);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .reminder-content {
            flex: 1;
            min-width: 0;
        }

        .reminder-time {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .reminder-title {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-primary);
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .reminder-settings {
            text-align: center;
        }

        .no-reminders {
            text-align: center;
            padding: 20px;
            color: var(--text-muted);
        }

        .no-reminders i {
            font-size: 24px;
            margin-bottom: 8px;
            opacity: 0.5;
        }

        .no-reminders p {
            font-size: 12px;
            margin: 0;
        }

        /* ── Notification Badge ─────────────────────────────────── */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* ── In-App Notifications ─────────────────────────────────── */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 300px;
            max-width: 400px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid var(--moss);
        }

        .notification.notification-warning {
            border-left-color: #f39c12;
        }

        .notification.notification-success {
            border-left-color: #27ae60;
        }

        .notification.notification-error {
            border-left-color: #e74c3c;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .notification-content {
            padding: 16px;
            padding-right: 40px;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .notification-close {
            position: absolute;
            top: 12px;
            right: 12px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .notification-close:hover {
            background: var(--moss-pale);
            color: var(--moss);
        }

        /* ── Event Analytics ─────────────────────────────────── */
        .analytics-container {
            padding: 16px 20px;
        }

        .analytics-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 16px;
            gap: 12px;
        }

        .analytics-item {
            text-align: center;
            flex: 1;
        }

        .analytics-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--moss);
            font-family: 'DM Serif Display', serif;
            line-height: 1;
            margin-bottom: 4px;
        }

        .analytics-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .analytics-chart {
            margin-bottom: 16px;
            text-align: center;
        }

        .analytics-chart canvas {
            max-width: 100%;
            height: auto !important;
        }

        .analytics-actions {
            text-align: center;
        }

        /* ── Analytics Modal Styles ─────────────────────────────────── */
        .analytics-report .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .stat-label {
            font-weight: 500;
            color: var(--text-secondary);
        }

        .stat-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .category-breakdown,
        .sources-breakdown {
            max-height: 200px;
            overflow-y: auto;
        }

        .category-stat,
        .source-stat {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .category-name,
        .source-name {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .category-count,
        .source-count {
            font-size: 13px;
            font-weight: 600;
            color: var(--moss);
        }

        /* ── Loading States ─────────────────────────────────── */
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--moss-pale);
            border-top: 4px solid var(--moss);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .calendar-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            background: var(--gray-light);
            border-radius: var(--radius-md);
            margin: 20px 0;
        }

        .calendar-loading .loading-spinner {
            margin-bottom: 16px;
        }

        /* ── Enhanced Sidebar Styles ───────────────────────────── */
        .sidebar-events-list,
        .sidebar-announcements-list {
            max-height: 300px;
            overflow-y: auto;
            padding: 0 8px;
        }

        .sidebar-event-item,
        .sidebar-announcement-item {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .sidebar-event-item:hover,
        .sidebar-announcement-item:hover {
            background: var(--moss-pale);
            padding-left: 16px;
        }

        .sidebar-event-item .event-time {
            font-size: 11px;
            color: var(--moss);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .sidebar-event-item .event-title {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .sidebar-event-item .event-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .sidebar-event-item .event-category {
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
            color: white;
        }

        .sidebar-announcement-item .announcement-title {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .sidebar-announcement-item .announcement-date {
            font-size: 11px;
            color: var(--text-muted);
        }

        .featured-event-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 16px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .featured-event-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .featured-event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .featured-event-header h4 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.3;
        }

        .featured-event-body {
            color: var(--text-secondary);
        }

        .featured-event-date {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--moss);
            margin-bottom: 8px;
        }

        .featured-event-description {
            font-size: 13px;
            line-height: 1.4;
        }

        /* ── Week View Styles (Original Design) ─────────────────── */
        .week-time-slot {
            background: var(--gray-light);
            padding: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 11px;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border);
        }

        .week-day-header {
            background: var(--moss-pale);
            padding: 12px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
            border-left: 1px solid var(--border);
        }

        .week-day-cell {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            border-left: 1px solid var(--border);
            padding: 8px;
            min-height: 60px;
            position: relative;
        }

        .week-event {
            background: var(--moss-pale);
            border-left: 3px solid var(--moss);
            padding: 6px 8px;
            margin-bottom: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
            line-height: 1.3;
        }

        .week-event:hover {
            background: var(--moss);
            color: white;
            transform: translateX(2px);
        }

        .week-event-time {
            font-size: 11px;
            color: var(--moss);
            font-weight: 600;
            margin-bottom: 2px;
        }

        .week-event:hover .week-event-time {
            color: white;
        }

        .week-event-title {
            color: var(--text-primary);
            font-weight: 500;
            line-height: 1.2;
        }

        .official-indicator {
            color: var(--moss);
            font-size: 10px;
            margin-left: 4px;
        }

        .week-event:hover .official-indicator {
            color: white;
        }

        /* ── Day Events Modal ───────────────────────────────── */
        .day-events-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .day-event-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .day-event-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .day-event-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .day-event-time {
            font-size: 12px;
            color: var(--moss);
            font-weight: 500;
        }

        .day-event-category {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            color: white;
        }

        .day-event-body {
            color: var(--text-secondary);
        }

        .day-event-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .day-event-description {
            font-size: 13px;
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .day-event-location {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* ── Responsive Design ─────────────────────────────────── */
        @media (max-width: 992px) {
            .events-search-filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-controls {
                flex-wrap: wrap;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .event-card-body {
                flex-direction: column;
            }

            .event-card-date {
                align-self: flex-start;
                min-width: auto;
                padding: 12px 20px;
                display: inline-flex;
                flex-direction: row;
                gap: 8px;
            }

            .event-date-day,
            .event-date-month {
                font-size: 0.875rem;
            }

            .category-checkboxes,
            .event-type-filters {
                flex-direction: column;
            }

            .form-check-inline {
                width: 100%;
            }
        }

        .event-list-item {
            padding: 16px;
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            background: var(--moss-pale);
            border-left: 4px solid var(--moss);
            transition: all 0.25s ease;
        }

        .event-list-item:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }

        .event-list-item h6 {
            margin: 0 0 8px 0;
            color: var(--ink);
            font-weight: 700;
            font-size: 14px;
        }

        .event-list-item p {
            margin: 0;
            font-size: 12.5px;
            color: var(--ink-muted);
        }

        .event-list-item p i {
            color: var(--moss);
            margin-right: 4px;
        }

        .modal-footer .btn-secondary {
            background: var(--moss-pale);
            border: 1.5px solid var(--border);
            color: var(--moss-dark);
            font-weight: 600;
            border-radius: 30px;
            padding: 8px 22px;
            font-size: 13px;
        }

        .modal-footer .btn-secondary:hover {
            background: var(--moss);
            color: white;
            border-color: var(--moss);
        }

        /* ── Category Tags (modal) ────────────────────────────── */
        .event-item-category {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .event-item-category.academic {
            background: #dbeafe;
            color: #1e40af;
        }

        .event-item-category.sports {
            background: #fee2e2;
            color: #991b1b;
        }

        .event-item-category.cultural {
            background: #ede9fe;
            color: #5b21b6;
        }

        .event-item-category.workshops {
            background: #fef3c7;
            color: #92400e;
        }

        .event-item-category.conferences {
            background: var(--moss-xlight);
            color: var(--moss-dark);
        }

        /* ── Pagination ───────────────────────────────────────── */
        .pagination-wrapper {
            margin-top: auto;
            padding-top: 32px;
        }

        .pagination .page-link {
            border: 1.5px solid var(--border);
            color: var(--moss-dark);
            border-radius: var(--radius-sm) !important;
            margin: 0 3px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .pagination .page-item.active .page-link {
            background: var(--moss);
            border-color: var(--moss);
            color: white;
        }

        .pagination .page-link:hover {
            background: var(--moss-pale);
        }

        /* ── Responsive ───────────────────────────────────────── */
        @media (max-width: 992px) {
            .events-list .event-item {
                flex: 1 1 100%;
            }
        }

        @media (max-width: 768px) {
            .events-list {
                flex-direction: column;
            }

            .events-list .event-item {
                flex: 1 1 100%;
            }
        }

        /* ── Official Event Styles ───────────────────────────── */
        .official-event {
            border-left: 4px solid var(--bs-primary);
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.05) 0%, rgba(13, 110, 253, 0.02) 100%);
        }

        .event-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .official-badge {
            background: var(--bs-primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .official-badge i {
            font-size: 10px;
        }

        .source-info {
            font-size: 12px;
            color: var(--text-secondary);
            margin: 4px 0;
            font-style: italic;
        }

        /* ── Filter Styles ─────────────────────────────────────── */
        .filter-section {
            padding: 12px;
            background: var(--gray-light);
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .filter-section .form-check {
            margin-bottom: 0;
        }

        .filter-section .form-check-input:checked {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        .categories .category-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px 10px 48px;
            margin-bottom: 6px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--ink);
            font-weight: 500;
            font-size: 14px;
            line-height: 1.4;
            transition: all 0.2s ease;
            position: relative;
            min-height: 44px; /* Touch-friendly height */
        }

        .categories .category-link:hover {
            background: var(--moss-pale);
            color: var(--moss);
        }

        .categories .category-link.active {
            background: var(--moss);
            color: white;
        }

        .categories .category-link span {
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .categories .category-link.active span {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Category indicators using ::before pseudo-elements */
        .categories .category-link::before {
            content: '';
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(0, 0, 0, 0.1);
            z-index: 1;
            transition: transform 0.2s ease;
        }

        .categories .category-link:hover::before {
            transform: translateY(-50%) scale(1.1);
        }

        .categories .category-link[data-category="all"]::before { 
            background: linear-gradient(45deg, #1abc9c, #e74c3c, #9b59b6) !important; 
        }
        .categories .category-link[data-category="Academic Calendar"]::before { 
            background-color: #059669 !important; 
        }
        .categories .category-link[data-category="Holidays"]::before { 
            background-color: #dc2626 !important; 
        }
        .categories .category-link[data-category="Health & Nutrition"]::before { 
            background-color: #16a34a !important; 
        }
        .categories .category-link[data-category="Governance & Elections"]::before { 
            background-color: #7c3aed !important; 
        }
        .categories .category-link[data-category="Assessments"]::before { 
            background-color: #ea580c !important; 
        }
        .categories .category-link[data-category="Professional Development"]::before { 
            background-color: #0891b2 !important; 
        }
        .categories .category-link[data-category="Remedial & Intervention"]::before { 
            background-color: #ca8a04 !important; 
        }
        .categories .category-link[data-category="Academic"]::before { 
            background-color: #1abc9c !important; 
        }
        .categories .category-link[data-category="Sports"]::before { 
            background-color: #e74c3c !important; 
        }
        .categories .category-link[data-category="Cultural"]::before { 
            background-color: #9b59b6 !important; 
        }
        .categories .category-link[data-category="Workshops"]::before { 
            background-color: #f59e0b !important; 
        }
        .categories .category-link[data-category="Conferences"]::before { 
            background-color: var(--moss) !important; 
        }

        /* Elementary Student Mode */
        body.elementary-mode .categories .category-link {
            font-size: 16px;
            font-weight: 600;
        }

        body.elementary-mode .event-card {
            border-radius: 12px;
            transform: scale(1.02);
        }

        body.elementary-mode .event-date-header {
            font-size: 18px;
        }

        /* High School Student Mode */
        body.highschool-mode .categories .category-link {
            font-size: 13px;
        }

        body.highschool-mode .event-card {
            border-left: 4px solid var(--moss);
        }

        /* Parent Mode */
        body.parent-mode .sidebar-item {
            border: 1px solid var(--border-light);
        }

        body.parent-mode .event-card {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
    <script src="https://kit.fontawesome.com/4ffbd94408.js" crossorigin="anonymous"></script>
</head>

<body class="events-page">

    <header id="header" class="header d-flex align-items-center sticky-top">
        <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">
            <a href="index.html" class="logo d-flex align-items-center">
                <img src="assets/img/Bagong_Pilipinas_logo.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 20px;">
                <img src="assets/img/DepED logo circle.png" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 0px;">
                <img src="assets/img/logo.jpg" alt="School Logo" class="me-2" style="height: 85px; width: auto; border-radius: 50px;">
                <h4 class="sitename mb-0">Buyoan National HighSchool</h4>
            </a>
            <div id="nav-placeholder"></div>
    </header>

    <main class="main">
        <div class="page-title">
            <div class="heading">
                <div class="container">
                    <div class="row d-flex justify-content-center text-center">
                        <div class="col-lg-8">
                            <h1 class="heading-title">Events</h1>
                            <p class="mb-0">"Stay updated with the latest events and activities at Buyoan National High School — where students, teachers, and the community come together to celebrate learning, achievement, and school spirit."</p>
                        </div>
                    </div>
                    <nav class="breadcrumbs">
                        <div class="container">
                            <ol>
                                <li><a href="index.html">Home</a></li>
                                <li class="current">Events</li>
                            </ol>
                        </div>
                    </nav>
                </div>

                <section id="events-2" class="events-2 section">
                    <div class="container-fluid">
                        <div class="row g-4">

                            <!-- ── Left: Events List ───────────────────── -->
                            <div class="col-lg-7">
                                <div class="events-list-container">
                                    <div class="events-section-header">
                                    <div class="section-label">Upcoming Events</div>
                                    <div class="header-content">
                                        <div class="header-main">
                                            <h2>What's Happening</h2>
                                            <div class="events-count-badge" id="events-count-badge" style="display:none;"><i class="fa-solid fa-calendar-check" style="color:var(--moss)"></i> <span id="events-count-text"></span></div>
                                        </div>
                                        <div class="export-controls">
                                            <div class="dropdown">
                                                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown">
                                                    <i class="fa-solid fa-download"></i> Export
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#" onclick="exportEvents('ics')">
                                                        <i class="fa-solid fa-calendar-plus"></i> Add to Calendar (ICS)
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="exportEvents('csv')">
                                                        <i class="fa-solid fa-file-csv"></i> Export to CSV
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="#" onclick="printEvents()">
                                                        <i class="fa-solid fa-print"></i> Print Events
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="#" onclick="syncCalendar()">
                                                        <i class="fa-solid fa-cloud-download-alt"></i> Sync with Google Calendar
                                                    </a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Advanced Search & Filter Bar -->
                                <div class="events-search-filter-bar">
                                    <div class="search-container">
                                        <div class="search-input-wrapper">
                                            <i class="fa-solid fa-search search-icon"></i>
                                            <input type="text" id="eventSearchInput" class="search-input" placeholder="Search events by title, description, or location...">
                                            <button type="button" class="search-clear-btn" id="searchClearBtn" style="display:none;">
                                                <i class="fa-solid fa-times-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="filter-controls">
                                        <div class="date-filter">
                                            <select id="dateRangeFilter" class="form-select form-select-sm">
                                                <option value="all">All Dates</option>
                                                <option value="today">Today</option>
                                                <option value="week">This Week</option>
                                                <option value="month">This Month</option>
                                                <option value="quarter">This Quarter</option>
                                                <option value="year">This Year</option>
                                            </select>
                                        </div>
                                        <div class="sort-filter">
                                            <select id="sortFilter" class="form-select form-select-sm">
                                                <option value="date-asc">Date (Earliest First)</option>
                                                <option value="date-desc">Date (Latest First)</option>
                                                <option value="title-asc">Title (A-Z)</option>
                                                <option value="title-desc">Title (Z-A)</option>
                                                <option value="category">Category</option>
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="advancedFilterToggle">
                                            <i class="fa-solid fa-filter"></i> Advanced
                                        </button>
                                    </div>
                                </div>

                                <!-- Advanced Filters Panel -->
                                <div class="advanced-filters-panel" id="advancedFiltersPanel" style="display:none;">
                                    <div class="filters-grid">
                                        <div class="filter-group">
                                            <label class="filter-label">Date Range</label>
                                            <div class="date-range-inputs">
                                                <input type="date" id="customDateFrom" class="form-control form-control-sm">
                                                <span class="date-separator">to</span>
                                                <input type="date" id="customDateTo" class="form-control form-control-sm">
                                            </div>
                                        </div>
                                        <div class="filter-group">
                                            <label class="filter-label">Categories</label>
                                            <div class="category-checkboxes">
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Academic Calendar">
                                                    <span class="form-check-label">Academic Calendar</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Holidays">
                                                    <span class="form-check-label">Holidays</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Assessments">
                                                    <span class="form-check-label">Assessments</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Health & Nutrition">
                                                    <span class="form-check-label">Health & Nutrition</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Governance & Elections">
                                                    <span class="form-check-label">Governance & Elections</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Professional Development">
                                                    <span class="form-check-label">Professional Development</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Remedial & Intervention">
                                                    <span class="form-check-label">Remedial & Intervention</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Academic">
                                                    <span class="form-check-label">Academic</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Sports">
                                                    <span class="form-check-label">Sports</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Cultural">
                                                    <span class="form-check-label">Cultural</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Workshops">
                                                    <span class="form-check-label">Workshops</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input category-filter" value="Conferences">
                                                    <span class="form-check-label">Conferences</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="filter-group">
                                            <label class="filter-label">Event Type</label>
                                            <div class="event-type-filters">
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input" id="officialEventsOnly">
                                                    <span class="form-check-label">Official DepEd Events</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input" id="multiDayEventsOnly">
                                                    <span class="form-check-label">Multi-Day Events</span>
                                                </label>
                                                <label class="form-check form-check-inline">
                                                    <input type="checkbox" class="form-check-input" id="teamEventsOnly">
                                                    <span class="form-check-label">Team-Based Events</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="filters-actions">
                                        <button type="button" class="btn btn-secondary btn-sm" id="clearFiltersBtn">
                                            <i class="fa-solid fa-rotate-right"></i> Clear All
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm" id="applyFiltersBtn">
                                            <i class="fa-solid fa-circle-check"></i> Apply Filters
                                        </button>
                                    </div>
                                </div>

                                <div class="events-list" id="events-list-container">
                                    <!-- Events are loaded dynamically via JS -->
                                    <div class="empty-state" id="events-loading-state">
                                        <i class="fa-solid fa-hourglass-half d-block"></i>
                                        <p>Loading events...</p>
                                    </div>
                                </div>

                                <?php
// Calculate pagination
$events_per_page = 20; // Changed to 20 for 2-column layout (10 per column)
$total_events = count($upcoming_events);
$total_pages = ceil($total_events / $events_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $events_per_page;
$paginated_events = array_slice($upcoming_events, $offset, $events_per_page);
?>

                                <?php if (count($upcoming_events) > 20): ?>
                                    <div class="pagination-wrapper">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo max(1, $current_page - 1); ?>" tabindex="-1">
                                                    <i class="fa-solid fa-chevron-left"></i>
                                                </a>
                                            </li>
                                            <?php 
                                            // Show page numbers with ellipsis for many pages
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);
                                            
                                            // Show first page
                                            if ($start_page > 1) {
                                                ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=1">1</a>
                                                </li>
                                                <?php
                                            }
                                            
                                            // Show middle pages
                                            for ($i = $start_page; $i <= $end_page; $i++) {
                                                ?>
                                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                                <?php
                                            }
                                            
                                            // Show ellipsis if needed
                                            if ($end_page < $total_pages - 1) {
                                                ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">...</span>
                                                </li>
                                                <?php
                                            }
                                            
                                            if ($end_page < $total_pages) {
                                                ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                                </li>
                                                <?php
                                            }
                                            ?>
                                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo min($total_pages, $current_page + 1); ?>">
                                                    <i class="fa-solid fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>

                            <!-- ── Right: Sidebar ─────────────────────── -->
                            <div class="col-lg-5">
                                <div class="sidebar">

                                    <!-- Calendar -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="fa-solid fa-calendar-days"></i></div>
                                            <h3>Event Calendar</h3>
                                        </div>
                                        <div class="calendar-view-controls">
                                            <div class="view-mode-switcher">
                                                <button class="view-btn active" data-view="month" onclick="switchCalendarView('month')">
                                                    <i class="fa-solid fa-calendar"></i> Month
                                                </button>
                                                <button class="view-btn" data-view="week" onclick="switchCalendarView('week')">
                                                    <i class="fa-solid fa-calendar-week"></i> Week
                                                </button>
                                                <button class="view-btn" data-view="day" onclick="switchCalendarView('day')">
                                                    <i class="fa-solid fa-calendar-day"></i> Day
                                                </button>
                                            </div>
                                        </div>
                                        <div class="calendar-container">
                                            <div class="calendar-wrapper">
                                                <div class="month" id="calendarMonth">
                                                    <ul>
                                                        <li class="prev" onclick="navigateCalendar(-1)">&#10094;</li>
                                                        <li id="monthYearDisplay"></li>
                                                        <li class="next" onclick="navigateCalendar(1)">&#10095;</li>
                                                    </ul>
                                                </div>
                                                <ul class="weekdays">
                                                    <li>Su</li>
                                                    <li>Mo</li>
                                                    <li>Tu</li>
                                                    <li>We</li>
                                                    <li>Th</li>
                                                    <li>Fr</li>
                                                    <li>Sa</li>
                                                </ul>
                                                <ul class="days" id="calendarDays"></ul>
                                            </div>
                                            
                                            <!-- Week View -->
                                            <div class="calendar-week-view" id="calendarWeekView">
                                                <div class="week-grid" id="weekGrid">
                                                    <!-- Week view will be generated by JavaScript -->
                                                </div>
                                            </div>
                                            
                                            <!-- Day View -->
                                            <div class="calendar-day-view" id="calendarDayView">
                                                <div class="day-timeline" id="dayTimeline">
                                                    <!-- Day view will be generated by JavaScript -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Featured Event -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="fa-solid fa-star"></i></div>
                                            <h3>Featured Event</h3>
                                        </div>
                                        <div class="featured-event-content">
                                            <?php if ($featured_event): ?>
                                                <?php $featuredDate = new DateTime($featured_event['event_date']); ?>
                                                <?php $featuredCat = strtolower($featured_event['category']); ?>
                                                <span class="event-category-tag <?php echo $featuredCat; ?>" style="margin-bottom:12px"><?php echo htmlspecialchars($featured_event['category']); ?></span>
                                                <h4><?php echo htmlspecialchars($featured_event['title']); ?></h4>
                                                <p><i class="fa-solid fa-calendar-days"></i> <?php echo $featuredDate->format('F j, Y'); ?></p>
                                                <?php if ($featured_event['description']): ?><p class="featured-desc"><?php echo htmlspecialchars($featured_event['description']); ?></p><?php endif; ?>
                                            <?php else: ?>
                                                <h4>No Featured Event</h4>
                                                <p>Check back later for upcoming events.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Event Categories -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="fa-solid fa-grip"></i></div>
                                            <h3>Event Categories</h3>
                                        </div>
                                        <div class="categories">
                                            <div class="filter-section mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="officialOnly" <?php echo $official_only ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="officialOnly">
                                                        <i class="fa-solid fa-certificate text-primary"></i> Official DepEd Events Only <span class="badge bg-primary text-white ms-2"><?php echo $official_events_count; ?></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <ul>
                                                <li><a href="#" data-category="all" class="category-link <?php echo !$category_filter ? 'active' : ''; ?>">All Events <span><?php echo array_sum($category_counts); ?></span></a></li>
                                                <li><a href="#" data-category="Academic Calendar" class="category-link <?php echo $category_filter === 'Academic Calendar' ? 'active' : ''; ?>">Academic Calendar <span><?php echo $category_counts['Academic Calendar'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Holidays" class="category-link <?php echo $category_filter === 'Holidays' ? 'active' : ''; ?>">Holidays <span><?php echo $category_counts['Holidays'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Health & Nutrition" class="category-link <?php echo $category_filter === 'Health & Nutrition' ? 'active' : ''; ?>">Health & Nutrition <span><?php echo $category_counts['Health & Nutrition'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Governance & Elections" class="category-link <?php echo $category_filter === 'Governance & Elections' ? 'active' : ''; ?>">Governance & Elections <span><?php echo $category_counts['Governance & Elections'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Assessments" class="category-link <?php echo $category_filter === 'Assessments' ? 'active' : ''; ?>">Assessments <span><?php echo $category_counts['Assessments'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Professional Development" class="category-link <?php echo $category_filter === 'Professional Development' ? 'active' : ''; ?>">Professional Development <span><?php echo $category_counts['Professional Development'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Remedial & Intervention" class="category-link <?php echo $category_filter === 'Remedial & Intervention' ? 'active' : ''; ?>">Remedial & Intervention <span><?php echo $category_counts['Remedial & Intervention'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Academic" class="category-link <?php echo $category_filter === 'Academic' ? 'active' : ''; ?>">Academic <span><?php echo $category_counts['Academic'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Sports" class="category-link <?php echo $category_filter === 'Sports' ? 'active' : ''; ?>">Sports <span><?php echo $category_counts['Sports'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Cultural" class="category-link <?php echo $category_filter === 'Cultural' ? 'active' : ''; ?>">Cultural <span><?php echo $category_counts['Cultural'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Workshops" class="category-link <?php echo $category_filter === 'Workshops' ? 'active' : ''; ?>">Workshops <span><?php echo $category_counts['Workshops'] ?? 0; ?></span></a></li>
                                                <li><a href="#" data-category="Conferences" class="category-link <?php echo $category_filter === 'Conferences' ? 'active' : ''; ?>">Conferences <span><?php echo $category_counts['Conferences'] ?? 0; ?></span></a></li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- School Days -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="fa-solid fa-calendar-week"></i></div>
                                            <h3>School Days</h3>
                                        </div>
                                        <div class="school-days-info">
                                            <div class="school-days-stat">
                                                <span class="school-days-number"><?php 
                                                    // Calculate total school days from DepEd calendar
                                                    $school_days_query = $conn->query("SELECT SUM(event_days) as total_days FROM events WHERE category IN ('Academic Calendar', 'Assessments') AND event_date BETWEEN '2025-06-16' AND '2026-03-31'");
                                                    $school_days_result = $school_days_query->fetch_assoc();
                                                    echo $school_days_result['total_days'] ?? 0;
                                                ?></span>
                                                <span class="school-days-label">Total School Days</span>
                                            </div>
                                            <div class="school-days-description">
                                                <small class="text-muted">SY 2025-2026 (DepEd Official)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event Reminders -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="fa-solid fa-bell"></i>
                                            <h3>Event Reminders</h3>
                                        </div>
                                        <div class="reminders-container">
                                            <div class="upcoming-reminders" id="upcomingReminders">
                                                <!-- Reminders will be populated by JavaScript -->
                                            </div>
                                            <div class="reminder-settings">
                                                <button class="btn btn-outline-primary btn-sm" onclick="showReminderSettings()">
                                                    <i class="fa-regular fa-bell" style="color: rgb(54, 148, 119);"></i> Subscribe
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Event Analytics -->
                                    <div class="sidebar-item">
                                        <div class="sidebar-item-header">
                                            <div class="header-icon"><i class="fa-solid fa-chart-line"></i>
                                            <h3>Event Analytics</h3>
                                        </div>
                                        <div class="analytics-container">
                                            <div class="analytics-summary">
                                                <div class="analytics-item">
                                                    <div class="analytics-number" id="totalEventsCount">0</div>
                                                    <div class="analytics-label">Total Events</div>
                                                </div>
                                                <div class="analytics-item">
                                                    <div class="analytics-number" id="upcomingEventsCount">0</div>
                                                    <div class="analytics-label">Upcoming</div>
                                                </div>
                                                <div class="analytics-item">
                                                    <div class="analytics-number" id="officialEventsCount">0</div>
                                                    <div class="analytics-label">Official</div>
                                                </div>
                                            </div>
                                            <div class="analytics-chart">
                                                <canvas id="eventsChart" width="200" height="100"></canvas>
                                            </div>
                                            <div class="analytics-actions">
                                                <button class="btn btn-outline-primary btn-sm" onclick="showAnalyticsModal()">
                                                    <i class="fa-solid fa-chart-bar"></i> View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </section>
    </main>

    <!-- Footer Placeholder -->
    <div id="footer-placeholder"></div>

    <!-- Scroll Top -->
    <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="fa-solid fa-arrow-up"></i></a>

    <!-- Preloader -->
    <div id="preloader"></div>

    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="assets/vendor/php-email-form/validate.js"></script> -->
    <!-- <script src="assets/vendor/swiper/swiper-bundle.min.js"></script> -->
    <!-- <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script> -->
    <!-- <script src="assets/vendor/glightbox/js/glightbox.min.js"></script> -->

    <!-- PureCounter Fallback -->
    <script>
        // Fallback for missing PureCounter
        if (typeof PureCounter === 'undefined') {
            window.PureCounter = function() {
                return {
                    init: function() {}
                };
            };
        }
        
        // Fallback for missing GLightbox
        if (typeof GLightbox === 'undefined') {
            window.GLightbox = function() {
                return {
                    init: function() {}
                };
            };
        }
    </script>

    <!-- Main JS File -->
    <script src="assets/js/main.js"></script>

    <!-- Include Navigation -->
    <script>
        fetch('nav.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('nav-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading navigation:', error));
    </script>

    <!-- Include Footer -->
    <script>
        fetch('footer.php')
            .then(response => response.text())
            .then(data => {
                document.getElementById('footer-placeholder').innerHTML = data;
            })
            .catch(error => console.error('Error loading footer:', error));
    </script>

    <!-- Include Modals -->
    <script>
        fetch('modals.php')
            .then(response => response.text())
            .then(data => {
                document.body.insertAdjacentHTML('beforeend', data);
                document.addEventListener('DOMContentLoaded', function() {
                    const loginBtn = document.querySelector('.btn-login');
                    const signupBtn = document.querySelector('.btn-signup');
                    if (loginBtn) {
                        loginBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            new bootstrap.Modal(document.getElementById('loginModal')).show();
                        });
                    }
                    if (signupBtn) {
                        signupBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            new bootstrap.Modal(document.getElementById('signupModal')).show();
                        });
                    }
                });
            })
            .catch(error => console.error('Error loading modals:', error));
    </script>

    <!-- Dynamic Events Loader -->
    <script>
        const ANNOUNCEMENTS_URL = 'ajax_handlers.php';
        const monthNamesShort = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const monthNamesFull = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

        function formatTime12h(time) {
            if (!time) return '';
            const parts = time.split(':');
            const h = parseInt(parts[0]);
            const m = parts[1];
            const ampm = h >= 12 ? 'PM' : 'AM';
            const h12 = (h % 12) || 12;
            return h12 + ':' + m + ' ' + ampm;
        }

        function buildEventDateRange(event) {
            const startDate = new Date(event.event_date + 'T00:00:00');
            const days = event.event_days ? parseInt(event.event_days) : 1;
            const endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + days - 1);

            const sM = startDate.getMonth();
            const sY = startDate.getFullYear();
            const eM = endDate.getMonth();
            const eY = endDate.getFullYear();

            if (sM === eM && sY === eY) {
                return monthNamesFull[sM] + ' ' + startDate.getDate() + '–' + endDate.getDate() + ', ' + sY;
            } else if (sY === eY) {
                return monthNamesFull[sM] + ' ' + startDate.getDate() + ' – ' + monthNamesFull[eM] + ' ' + endDate.getDate() + ', ' + sY;
            } else {
                return monthNamesFull[sM] + ' ' + startDate.getDate() + ', ' + sY + ' – ' + monthNamesFull[eM] + ' ' + endDate.getDate() + ', ' + eY;
            }
        }

        function renderEventCard(event) {
            const startDate = new Date(event.event_date + 'T00:00:00');
            const day = String(startDate.getDate()).padStart(2, '0');
            const mon = monthNamesShort[startDate.getMonth()];
            const catClass = event.category ? event.category.toLowerCase().replace(/\s+/g, '-') : '';
            const dateRange = buildEventDateRange(event);
            const days = event.event_days ? parseInt(event.event_days) : 1;
            const startTimeStr = formatTime12h(event.event_start_time);
            const endTimeStr = formatTime12h(event.event_end_time);
            let timeLine = '';
            if (startTimeStr && endTimeStr) timeLine = startTimeStr + ' – ' + endTimeStr;
            else if (startTimeStr) timeLine = startTimeStr;
            else if (endTimeStr) timeLine = endTimeStr;
            const buttonText = event.team_based == 1 ? 'Join Now' : 'Learn More';
            const desc = event.description ? `<p>${escapeHtml(event.description)}</p>` : '';
            const timeHtml = timeLine ? `<p><i class="fa-solid fa-clock"></i> ${timeLine}</p>` : '';
            const daysHtml = days > 1 ? `<p><i class="fa-solid fa-layer-group"></i> ${days} days</p>` : '';
            
            // Add official badge if event is official
            const officialBadge = event.is_official == 1 ? `<span class="official-badge"><i class="fa-solid fa-certificate"></i> DepEd Official</span>` : '';
            const sourceInfo = event.source ? `<p class="source-info"><i class="fa-solid fa-info-circle"></i> Source: ${escapeHtml(event.source)}</p>` : '';

            return `
            <div class="event-item ${event.is_official == 1 ? 'official-event' : ''}">
                <div class="event-date">
                    <span class="day">${day}</span>
                    <span class="month">${mon}</span>
                </div>
                <div class="event-content">
                    <div class="event-header">
                        <span class="event-category-tag ${catClass}">${escapeHtml(event.category)}</span>
                        ${officialBadge}
                    </div>
                    <h3>${escapeHtml(event.title)}</h3>
                    <div class="event-meta">
                        ${timeHtml}
                        <p><i class="fa-solid fa-calendar-days"></i> ${dateRange}</p>
                        ${daysHtml}
                        ${sourceInfo}
                    </div>
                    ${desc}
                    <a href="user_account/event-details.php?id=${event.id}" class="btn-event">${buttonText} <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>`;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function loadFilteredEvents(category = 'all', official = false, page = 1) {
            console.log('Loading filtered events:', { category, official, page });
            
            const container = document.getElementById('events-list-container');
            const countBadge = document.getElementById('events-count-badge');
            const countText = document.getElementById('events-count-text');

            // Show loading state
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-hourglass-half d-block"></i>
                    <p>Loading filtered events...</p>
                </div>
            `;

            console.log('Making AJAX request to:', ANNOUNCEMENTS_URL);
            
            fetch(ANNOUNCEMENTS_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'get_filtered_events',
                        category: category,
                        official: official ? '1' : '0',
                        page: page,
                        limit: 20 // Get 20 events for 2-column layout
                    })
                })
                .then(res => {
                    console.log('Response status:', res.status);
                    return res.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.status === 'success' && data.events && data.events.length > 0) {
                        // Create 2-column grid layout
                        const leftEvents = data.events.slice(0, 10); // First 10 for left column
                        const rightEvents = data.events.slice(10, 20); // Next 10 for right column
                        
                        const renderedHTML = `
                            <div class="events-grid-left">
                                ${leftEvents.map(renderEventCard).join('')}
                            </div>
                            <div class="events-grid-right">
                                ${rightEvents.map(renderEventCard).join('')}
                            </div>
                        `;
                        
                        console.log('Events to render:', data.events);
                        console.log('Rendered HTML length:', renderedHTML.length);
                        container.innerHTML = renderedHTML;
                        
                        const n = data.total_events || data.events.length;
                        countText.textContent = n + ' event' + (n > 1 ? 's' : '') + ' found';
                        countBadge.style.display = 'inline-flex';
                        
                        // Update pagination if needed
                        if (data.pagination) {
                            updatePagination(data.pagination, category, official);
                        }
                    } else {
                        console.log('No events found or error in response');
                        container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-filter d-block"></i>
                        <p>No events found matching your criteria.</p>
                    </div>`;
                        countBadge.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading filtered events:', error);
                    container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-triangle-exclamation d-block"></i>
                        <p>Error loading events. Please try again.</p>
                    </div>`;
                    countBadge.style.display = 'none';
                });
        }

        function loadUpcomingEvents(page = 1) {
            const container = document.getElementById('events-list-container');
            const countBadge = document.getElementById('events-count-badge');
            const countText = document.getElementById('events-count-text');

            fetch(ANNOUNCEMENTS_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        action: 'get_upcoming_events',
                        page: page,
                        limit: 20 // Get 20 events for 2-column layout
                    })
                })
                .then(res => {
                    console.log('Response status:', res.status);
                    return res.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.events && data.events.length > 0) {
                        // Create 2-column grid layout
                        const leftEvents = data.events.slice(0, 10); // First 10 for left column
                        const rightEvents = data.events.slice(10, 20); // Next 10 for right column
                        
                        const renderedHTML = `
                            <div class="events-grid-left">
                                ${leftEvents.map(renderEventCard).join('')}
                            </div>
                            <div class="events-grid-right">
                                ${rightEvents.map(renderEventCard).join('')}
                            </div>
                        `;
                        
                        container.innerHTML = renderedHTML;
                        const n = data.total_events || data.events.length;
                        countText.textContent = n + ' event' + (n > 1 ? 's' : '') + ' scheduled';
                        countBadge.style.display = 'inline-flex';
                        
                        // Update pagination if needed
                        if (data.pagination) {
                            updatePagination(data.pagination, 'all', false);
                        }
                    } else {
                        container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-calendar-xmark d-block"></i>
                        <p>No upcoming events scheduled. Check back soon!</p>
                    </div>`;
                        countBadge.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading upcoming events:', error);
                    container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-triangle-exclamation d-block"></i>
                        <p>Failed to load events. Please try again.</p>
                    </div>`;
                    countBadge.style.display = 'none';
                });
        }

        function updatePagination(pagination, category = null, official = false) {
            // Update pagination links if they exist
            const paginationWrapper = document.querySelector('.pagination-wrapper');
            if (paginationWrapper && pagination.total_pages > 1) {
                let paginationHTML = `
                    <ul class="pagination justify-content-center">
                        <li class="page-item ${pagination.current_page <= 1 ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="loadFilteredEvents('${category}', ${official}, ${Math.max(1, pagination.current_page - 1)}); return false;">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        </li>
                `;
                
                for (let i = 1; i <= pagination.total_pages; i++) {
                    paginationHTML += `
                        <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="loadFilteredEvents('${category}', ${official}, ${i}); return false;">${i}</a>
                        </li>
                    `;
                }
                
                paginationHTML += `
                        <li class="page-item ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="loadFilteredEvents('${category}', ${official}, ${Math.min(pagination.total_pages, pagination.current_page + 1)}); return false;">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                `;
                
                paginationWrapper.innerHTML = paginationHTML;
            }
        }

        // ============================================================
        //  ADVANCED SEARCH & FILTERING FUNCTIONALITY
        // ============================================================
        class EventSearchFilter {
            constructor() {
                this.searchInput = document.getElementById('eventSearchInput');
                this.searchClearBtn = document.getElementById('searchClearBtn');
                this.advancedFilterToggle = document.getElementById('advancedFilterToggle');
                this.advancedFiltersPanel = document.getElementById('advancedFiltersPanel');
                this.dateRangeFilter = document.getElementById('dateRangeFilter');
                this.sortFilter = document.getElementById('sortFilter');
                this.applyFiltersBtn = document.getElementById('applyFiltersBtn');
                this.clearFiltersBtn = document.getElementById('clearFiltersBtn');
                
                this.filters = {
                    search: '',
                    categories: [],
                    dateRange: 'all',
                    customDateFrom: '',
                    customDateTo: '',
                    officialOnly: false,
                    multiDayOnly: false,
                    teamEventsOnly: false,
                    sortBy: 'date-asc'
                };
                
                this.allEvents = [];
                this.filteredEvents = [];
                
                this.init();
            }
            
            init() {
                this.bindEvents();
                this.loadEvents();
            }
            
            bindEvents() {
                // Search functionality
                if (this.searchInput) {
                    this.searchInput.addEventListener('input', (e) => {
                        this.filters.search = e.target.value;
                        this.toggleClearButton();
                        this.applyFilters();
                    });
                }
                
                if (this.searchClearBtn) {
                    this.searchClearBtn.addEventListener('click', () => {
                        this.clearSearch();
                    });
                }
                
                // Advanced filter toggle
                if (this.advancedFilterToggle) {
                    this.advancedFilterToggle.addEventListener('click', () => {
                        this.toggleAdvancedFilters();
                    });
                }
                
                // Date range filter
                if (this.dateRangeFilter) {
                    this.dateRangeFilter.addEventListener('change', (e) => {
                        this.filters.dateRange = e.target.value;
                        this.applyFilters();
                    });
                }
                
                // Sort filter
                if (this.sortFilter) {
                    this.sortFilter.addEventListener('change', (e) => {
                        this.filters.sortBy = e.target.value;
                        this.applyFilters();
                    });
                }
                
                // Custom date inputs
                const dateFrom = document.getElementById('customDateFrom');
                const dateTo = document.getElementById('customDateTo');
                
                if (dateFrom) {
                    dateFrom.addEventListener('change', (e) => {
                        this.filters.customDateFrom = e.target.value;
                    });
                }
                
                if (dateTo) {
                    dateTo.addEventListener('change', (e) => {
                        this.filters.customDateTo = e.target.value;
                    });
                }
                
                // Category checkboxes
                document.querySelectorAll('.category-filter').forEach(checkbox => {
                    checkbox.addEventListener('change', () => {
                        this.updateCategoryFilters();
                    });
                });
                
                // Event type checkboxes
                const officialEventsOnly = document.getElementById('officialEventsOnly');
                const multiDayEventsOnly = document.getElementById('multiDayEventsOnly');
                const teamEventsOnly = document.getElementById('teamEventsOnly');
                
                if (officialEventsOnly) {
                    officialEventsOnly.addEventListener('change', (e) => {
                        this.filters.officialOnly = e.target.checked;
                    });
                }
                
                if (multiDayEventsOnly) {
                    multiDayEventsOnly.addEventListener('change', (e) => {
                        this.filters.multiDayOnly = e.target.checked;
                    });
                }
                
                if (teamEventsOnly) {
                    teamEventsOnly.addEventListener('change', (e) => {
                        this.filters.teamEventsOnly = e.target.checked;
                    });
                }
                
                // Event type checkboxes
                const officialCheckbox = document.getElementById('officialEventsOnly');
                const multiDayCheckbox = document.getElementById('multiDayEventsOnly');
                const teamCheckbox = document.getElementById('teamEventsOnly');
                
                if (officialCheckbox) {
                    officialCheckbox.addEventListener('change', (e) => {
                        this.filters.officialOnly = e.target.checked;
                    });
                }
                
                if (multiDayCheckbox) {
                    multiDayCheckbox.addEventListener('change', (e) => {
                        this.filters.multiDayOnly = e.target.checked;
                    });
                }
                
                if (teamCheckbox) {
                    teamCheckbox.addEventListener('change', (e) => {
                        this.filters.teamEventsOnly = e.target.checked;
                    });
                }
                
                // Apply and clear buttons
                if (this.applyFiltersBtn) {
                    this.applyFiltersBtn.addEventListener('click', () => {
                        this.applyFilters();
                        this.hideAdvancedFilters();
                    });
                }
                
                if (this.clearFiltersBtn) {
                    this.clearFiltersBtn.addEventListener('click', () => {
                        this.clearAllFilters();
                    });
                }
                
                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && e.key === 'k') {
                        e.preventDefault();
                        this.searchInput?.focus();
                    }
                    if (e.key === 'Escape') {
                        this.hideAdvancedFilters();
                        this.clearSearch();
                    }
                });
            }
            
            async loadEvents() {
                try {
                    this.showLoadingState();
                    const response = await fetch(ANNOUNCEMENTS_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'action=get_all_events'
                    });
                    
                    const data = await response.json();
                    if (data.status === 'success') {
                        this.allEvents = data.events || [];
                        console.log('EventSearchFilter loaded events:', this.allEvents.length);
                        this.applyFilters();
                    } else {
                        this.showError('Failed to load events');
                    }
                } catch (error) {
                    console.error('EventSearchFilter load error:', error);
                    this.showError('Error loading events');
                }
            }
            
            applyFilters() {
                this.filteredEvents = this.allEvents.filter(event => {
                    // Search filter
                    if (this.filters.search) {
                        const searchTerm = this.filters.search.toLowerCase();
                        const matchesSearch = 
                            event.title.toLowerCase().includes(searchTerm) ||
                            (event.description && event.description.toLowerCase().includes(searchTerm)) ||
                            (event.location && event.location.toLowerCase().includes(searchTerm));
                        
                        if (!matchesSearch) return false;
                    }
                    
                    // Category filter
                    if (this.filters.categories.length > 0) {
                        if (!this.filters.categories.includes(event.category)) return false;
                    }
                    
                    // Date range filter
                    if (!this.matchesDateFilter(event.event_date)) return false;
                    
                    // Official events filter
                    if (this.filters.officialOnly && !event.is_official) return false;
                    
                    // Multi-day events filter
                    if (this.filters.multiDayOnly && (!event.event_days || event.event_days <= 1)) return false;
                    
                    // Team events filter
                    if (this.filters.teamEventsOnly && !event.team_based) return false;
                    
                    return true;
                });
                
                // Sort results
                this.sortEvents();
                
                // Render results
                this.renderEvents();
                this.updateResultsCount();
            }
            
            matchesDateFilter(eventDate) {
                const today = new Date();
                const event = new Date(eventDate);
                
                switch (this.filters.dateRange) {
                    case 'all':
                        return true;
                    case 'today':
                        return this.isSameDay(event, today);
                    case 'week':
                        const weekEnd = new Date(today);
                        weekEnd.setDate(today.getDate() + 7);
                        return event >= today && event <= weekEnd;
                    case 'month':
                        return event.getMonth() === today.getMonth() && 
                               event.getFullYear() === today.getFullYear();
                    case 'quarter':
                        const quarter = Math.floor(today.getMonth() / 3);
                        const eventQuarter = Math.floor(event.getMonth() / 3);
                        return quarter === eventQuarter && 
                               event.getFullYear() === today.getFullYear();
                    case 'year':
                        return event.getFullYear() === today.getFullYear();
                    case 'custom':
                        if (this.filters.customDateFrom && this.filters.customDateTo) {
                            const from = new Date(this.filters.customDateFrom);
                            const to = new Date(this.filters.customDateTo);
                            return event >= from && event <= to;
                        }
                        return true;
                    default:
                        return true;
                }
            }
            
            isSameDay(date1, date2) {
                return date1.getFullYear() === date2.getFullYear() &&
                       date1.getMonth() === date2.getMonth() &&
                       date1.getDate() === date2.getDate();
            }
            
            sortEvents() {
                this.filteredEvents.sort((a, b) => {
                    switch (this.filters.sortBy) {
                        case 'date-asc':
                            return new Date(a.event_date) - new Date(b.event_date);
                        case 'date-desc':
                            return new Date(b.event_date) - new Date(a.event_date);
                        case 'title-asc':
                            return a.title.localeCompare(b.title);
                        case 'title-desc':
                            return b.title.localeCompare(a.title);
                        case 'category':
                            return a.category.localeCompare(b.category);
                        default:
                            return new Date(a.event_date) - new Date(b.event_date);
                    }
                });
            }
            
            renderEvents() {
                const container = document.getElementById('events-list-container');
                if (!container) return;
                
                if (this.filteredEvents.length === 0) {
                    container.innerHTML = this.getEmptyStateHTML();
                    return;
                }
                
                // Create 2-column grid layout (same as our loadFilteredEvents)
                const leftEvents = this.filteredEvents.slice(0, 10); // First 10 for left column
                const rightEvents = this.filteredEvents.slice(10, 20); // Next 10 for right column
                
                const renderedHTML = `
                    <div class="events-grid-left">
                        ${leftEvents.map(event => this.createEventCard(event)).join('')}
                    </div>
                    <div class="events-grid-right">
                        ${rightEvents.map(event => this.createEventCard(event)).join('')}
                    </div>
                `;
                
                container.innerHTML = renderedHTML;
                
                // Add fade-in animation
                container.querySelectorAll('.event-card').forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 50);
                });
            }
            
            createEventCard(event) {
                const eventDate = new Date(event.event_date);
                const day = eventDate.getDate();
                const month = eventDate.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
                
                const officialBadge = event.is_official ? 
                    `<div class="official-badge">
                        <i class="fa-solid fa-certificate"></i> Official DepEd
                    </div>` : '';
                
                const categoryColors = {
                    'Academic Calendar': '#1abc9c',
                    'Holidays': '#e74c3c',
                    'Health & Nutrition': '#27ae60',
                    'Governance & Elections': '#8e44ad',
                    'Assessments': '#f39c12',
                    'Professional Development': '#3498db',
                    'Remedial & Intervention': '#e67e22',
                    'Academic': '#1abc9c',
                    'Sports': '#e74c3c',
                    'Cultural': '#9b59b6',
                    'Workshops': '#3498db',
                    'Conferences': '#2c3e50'
                };
                
                const categoryColor = categoryColors[event.category] || '#95a5a6';
                
                return `
                    <div class="event-card" data-event-id="${event.id}">
                        <div class="event-card-header">
                            <h3 class="event-card-title">${this.escapeHtml(event.title)}</h3>
                            <div class="event-card-badges">
                                ${officialBadge}
                                <div class="event-category-badge" style="background: ${categoryColor}20; color: ${categoryColor}">
                                    ${event.category}
                                </div>
                            </div>
                        </div>
                        <div class="event-card-body">
                            <div class="event-card-content">
                                ${event.description ? `<p class="event-card-description">${this.escapeHtml(event.description)}</p>` : ''}
                                <div class="event-card-meta">
                                    <div class="event-meta-item">
                                        <i class="fa-solid fa-calendar-days"></i>
                                        <span>${eventDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
                                    </div>
                                    ${event.location ? `
                                        <div class="event-meta-item">
                                            <i class="fa-solid fa-location-dot"></i>
                                            <span>${this.escapeHtml(event.location)}</span>
                                        </div>
                                    ` : ''}
                                    ${event.event_days > 1 ? `
                                        <div class="event-meta-item">
                                            <i class="fa-solid fa-calendar-days"></i>
                                            <span>${event.event_days} days</span>
                                        </div>
                                    ` : ''}
                                    ${event.team_based ? `
                                        <div class="event-meta-item">
                                            <i class="fa-solid fa-users"></i>
                                            <span>Team Event</span>
                                        </div>
                                    ` : ''}
                                </div>
                                <div class="event-card-actions">
                                    <a href="user_account/event-details.php?id=${event.id}" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-eye"></i> View Details
                                    </a>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="eventReminderSystem.createReminderForEvent(${JSON.stringify(event).replace(/"/g, '&quot;')})">
                                        <i class="fa-solid fa-bell"></i> Remind
                                    </button>
                                    ${event.source ? `
                                        <span class="text-muted small">
                                            <i class="fa-solid fa-info-circle"></i> ${this.escapeHtml(event.source)}
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                            <div class="event-card-date">
                                <div class="event-date-day">${day}</div>
                                <div class="event-date-month">${month}</div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            getEmptyStateHTML() {
                const hasFilters = this.filters.search || 
                                 this.filters.categories.length > 0 || 
                                 this.filters.dateRange !== 'all';
                
                if (hasFilters) {
                    return `
                        <div class="empty-state">
                            <i class="fa-solid fa-search"></i>
                            <h3>No events found</h3>
                            <p>Try adjusting your search criteria or filters</p>
                            <button class="btn btn-outline-primary" onclick="eventSearchFilter.clearAllFilters()">
                                <i class="fa-solid fa-rotate-right"></i> Clear Filters
                            </button>
                        </div>
                    `;
                } else {
                    return `
                        <div class="empty-state">
                            <i class="fa-solid fa-calendar-xmark"></i>
                            <h3>No upcoming events</h3>
                            <p>Check back later for new events and activities</p>
                        </div>
                    `;
                }
            }
            
            updateResultsCount() {
                const countBadge = document.getElementById('events-count-badge');
                const countText = document.getElementById('events-count-text');
                
                if (countBadge && countText) {
                    countText.textContent = `${this.filteredEvents.length} event${this.filteredEvents.length !== 1 ? 's' : ''} found`;
                    countBadge.style.display = 'block';
                }
            }
            
            toggleClearButton() {
                if (this.searchClearBtn) {
                    this.searchClearBtn.style.display = this.filters.search ? 'block' : 'none';
                }
            }
            
            clearSearch() {
                if (this.searchInput) {
                    this.searchInput.value = '';
                    this.filters.search = '';
                    this.toggleClearButton();
                    this.applyFilters();
                }
            }
            
            toggleAdvancedFilters() {
                if (this.advancedFiltersPanel) {
                    const isVisible = this.advancedFiltersPanel.style.display !== 'none';
                    this.advancedFiltersPanel.style.display = isVisible ? 'none' : 'block';
                    
                    if (!isVisible) {
                        // Focus first input when opening
                        const firstInput = this.advancedFiltersPanel.querySelector('input, select');
                        firstInput?.focus();
                    }
                }
            }
            
            hideAdvancedFilters() {
                if (this.advancedFiltersPanel) {
                    this.advancedFiltersPanel.style.display = 'none';
                }
            }
            
            updateCategoryFilters() {
                this.filters.categories = [];
                document.querySelectorAll('.category-filter:checked').forEach(checkbox => {
                    this.filters.categories.push(checkbox.value);
                });
            }
            
            clearAllFilters() {
                // Reset all filter states
                this.filters = {
                    search: '',
                    categories: [],
                    dateRange: 'all',
                    customDateFrom: '',
                    customDateTo: '',
                    officialOnly: false,
                    multiDayOnly: false,
                    teamEventsOnly: false,
                    sortBy: 'date-asc'
                };
                
                // Reset UI elements
                if (this.searchInput) this.searchInput.value = '';
                if (this.dateRangeFilter) this.dateRangeFilter.value = 'all';
                if (this.sortFilter) this.sortFilter.value = 'date-asc';
                
                // Reset custom date inputs
                const dateFrom = document.getElementById('customDateFrom');
                const dateTo = document.getElementById('customDateTo');
                if (dateFrom) dateFrom.value = '';
                if (dateTo) dateTo.value = '';
                
                // Uncheck all checkboxes
                document.querySelectorAll('.category-filter').forEach(cb => cb.checked = false);
                document.getElementById('officialEventsOnly').checked = false;
                document.getElementById('multiDayEventsOnly').checked = false;
                document.getElementById('teamEventsOnly').checked = false;
                
                // Hide advanced panel
                this.hideAdvancedFilters();
                
                // Apply filters
                this.applyFilters();
            }
            
            showLoadingState() {
                const container = document.getElementById('events-list-container');
                if (container) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <p>Loading events...</p>
                        </div>
                    `;
                }
            }
            
            showError(message) {
                const container = document.getElementById('events-list-container');
                if (container) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <h3>Error</h3>
                            <p>${message}</p>
                            <button class="btn btn-primary" onclick="eventSearchFilter.loadEvents()">
                                <i class="fa-solid fa-rotate-right"></i> Try Again
                            </button>
                        </div>
                    `;
                }
            }
            
            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }

        // ============================================================
        //  EVENT EXPORT & SYNC FUNCTIONALITY
        // ============================================================
        function exportEvents(format) {
            const events = window.eventSearchFilter?.filteredEvents || [];
            
            if (events.length === 0) {
                alert('No events to export');
                return;
            }
            
            switch(format) {
                case 'ics':
                    exportToICS(events);
                    break;
                case 'csv':
                    exportToCSV(events);
                    break;
                default:
                    alert('Export format not supported');
            }
        }

        function exportToICS(events) {
            let icsContent = 'BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//BUNHS School System//Events Calendar//EN\n';
            
            events.forEach(event => {
                const startDate = new Date(event.event_date);
                const endDate = new Date(startDate);
                endDate.setDate(startDate.getDate() + (event.event_days || 1) - 1);
                
                const formatDate = (date) => {
                    return date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
                };
                
                icsContent += `BEGIN:VEVENT\n`;
                icsContent += `UID:event-${event.id}@bunhs.edu.ph\n`;
                icsContent += `DTSTART:${formatDate(startDate)}\n`;
                icsContent += `DTEND:${formatDate(endDate)}\n`;
                icsContent += `SUMMARY:${escapeICS(event.title)}\n`;
                if (event.description) {
                    icsContent += `DESCRIPTION:${escapeICS(event.description)}\n`;
                }
                if (event.location) {
                    icsContent += `LOCATION:${escapeICS(event.location)}\n`;
                }
                icsContent += `END:VEVENT\n`;
            });
            
            icsContent += 'END:VCALENDAR';
            
            downloadFile(icsContent, 'bunhs-events.ics', 'text/calendar');
        }

        function exportToCSV(events) {
            const headers = ['Title', 'Date', 'End Date', 'Category', 'Location', 'Description', 'Source', 'Official'];
            const csvContent = [
                headers.join(','),
                ...events.map(event => [
                    escapeCSV(event.title),
                    event.event_date,
                    event.event_days > 1 ? calculateEndDate(event.event_date, event.event_days) : event.event_date,
                    escapeCSV(event.category),
                    escapeCSV(event.location || ''),
                    escapeCSV(event.description || ''),
                    escapeCSV(event.source || ''),
                    event.is_official ? 'Yes' : 'No'
                ].join(','))
            ].join('\n');
            
            downloadFile(csvContent, 'bunhs-events.csv', 'text/csv');
        }

        function printEvents() {
            const events = window.eventSearchFilter?.filteredEvents || [];
            
            if (events.length === 0) {
                alert('No events to print');
                return;
            }
            
            const printWindow = window.open('', '_blank');
            const html = generatePrintHTML(events);
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.print();
        }

        function generatePrintHTML(events) {
            const eventsHTML = events.map(event => `
                <div class="event-item" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #1abc9c;">${event.title}</h3>
                    <p style="margin: 5px 0;"><strong>Date:</strong> ${formatEventDate(event.event_date, event.event_days)}</p>
                    <p style="margin: 5px 0;"><strong>Category:</strong> ${event.category}</p>
                    ${event.location ? `<p style="margin: 5px 0;"><strong>Location:</strong> ${event.location}</p>` : ''}
                    ${event.description ? `<p style="margin: 5px 0;"><strong>Description:</strong> ${event.description}</p>` : ''}
                    ${event.is_official ? '<p style="margin: 5px 0; color: #27ae60;"><strong>✓ Official DepEd Event</strong></p>' : ''}
                    ${event.source ? `<p style="margin: 5px 0; font-size: 12px; color: #666;"><em>Source: ${event.source}</em></p>` : ''}
                </div>
            `).join('');
            
            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>BUNHS Events - Print</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .header h1 { color: #1abc9c; }
                        .header p { color: #666; }
                        .event-item { page-break-inside: avoid; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Buyoan National High School Events</h1>
                        <p>Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    <div class="events-list">
                        ${eventsHTML}
                    </div>
                </body>
                </html>
            `;
        }

        function syncCalendar() {
            // This would integrate with Google Calendar API
            // For now, show a modal with instructions
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Sync with Google Calendar</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h6>Option 1: Export ICS File</h6>
                            <p>Download the ICS file and import it to your Google Calendar:</p>
                            <ol>
                                <li>Click "Add to Calendar (ICS)" from the Export menu</li>
                                <li>Open Google Calendar</li>
                                <li>Click the "+" icon next to "Other calendars"</li>
                                <li>Select "Import" and upload the ICS file</li>
                            </ol>
                            
                            <h6 class="mt-4">Option 2: Manual Sync</h6>
                            <p>For automatic sync, contact the school administrator to set up Google Calendar integration.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="exportEvents('ics'); bootstrap.Modal.getInstance(modal).hide();">
                                <i class="fa-solid fa-download"></i> Export ICS File
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        }

        // Helper functions
        function escapeICS(text) {
            return text.replace(/\\|;|,|\n/g, '\\$&').replace(/\r/g, '');
        }

        function escapeCSV(text) {
            return text.replace(/"/g, '""');
        }

        function calculateEndDate(startDate, days) {
            const date = new Date(startDate);
            date.setDate(date.getDate() + days - 1);
            return date.toISOString().split('T')[0];
        }

        function formatEventDate(date, days) {
            const startDate = new Date(date);
            if (days > 1) {
                const endDate = new Date(startDate);
                endDate.setDate(startDate.getDate() + days - 1);
                return `${startDate.toLocaleDateString()} - ${endDate.toLocaleDateString()}`;
            }
            return startDate.toLocaleDateString();
        }

        function downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // ============================================================
        //  EVENT REMINDER SYSTEM
        // ============================================================
        class EventReminderSystem {
            constructor() {
                this.reminders = this.loadReminders();
                this.upcomingRemindersContainer = document.getElementById('upcomingReminders');
                this.init();
            }

            init() {
                this.renderUpcomingReminders();
                this.checkPendingReminders();
                
                // Check reminders more frequently for precision
                // Check every 5 seconds for better precision with short intervals
                setInterval(() => this.checkPendingReminders(), 5000);
                
                // Also check every second for very short intervals (like 1 second)
                setInterval(() => this.checkVeryShortReminders(), 1000);
                
                // Request notification permission
                this.requestNotificationPermission();
            }

            loadReminders() {
                const stored = localStorage.getItem('eventReminders');
                return stored ? JSON.parse(stored) : [];
            }

            saveReminders() {
                localStorage.setItem('eventReminders', JSON.stringify(this.reminders));
            }

            addReminder(eventId, eventTitle, eventDate, reminderTime) {
                const reminder = {
                    id: Date.now(),
                    eventId: eventId,
                    eventTitle: eventTitle,
                    eventDate: eventDate,
                    reminderTime: reminderTime,
                    notified: false,
                    createdAt: new Date().toISOString()
                };

                this.reminders.push(reminder);
                this.saveReminders();
                this.renderUpcomingReminders();
                
                this.showNotification('Reminder Set', `You'll be reminded about "${eventTitle}"`, 'info');
            }

            removeReminder(reminderId) {
                this.reminders = this.reminders.filter(r => r.id !== reminderId);
                this.saveReminders();
                this.renderUpcomingReminders();
            }

            getUpcomingReminders() {
                const now = new Date();
                const nextWeek = new Date();
                nextWeek.setDate(now.getDate() + 7);

                return this.reminders
                    .filter(reminder => {
                        const reminderDate = new Date(reminder.reminderTime);
                        return reminderDate >= now && reminderDate <= nextWeek && !reminder.notified;
                    })
                    .sort((a, b) => new Date(a.reminderTime) - new Date(b.reminderTime));
            }

            renderUpcomingReminders() {
                if (!this.upcomingRemindersContainer) return;

                const upcoming = this.getUpcomingReminders();

                if (upcoming.length === 0) {
                    this.upcomingRemindersContainer.innerHTML = `
                        <div class="no-reminders">
                            <i class="fa-solid fa-bell-slash"></i>
                            <p>No upcoming reminders</p>
                        </div>
                    `;
                    return;
                }

                const remindersHTML = upcoming.map(reminder => {
                    const reminderDate = new Date(reminder.reminderTime);
                    const timeAgo = this.getTimeAgo(reminderDate);
                    const icon = this.getReminderIcon(reminderDate);

                    return `
                        <div class="reminder-item" onclick="window.location.href='user_account/event-details.php?id=${reminder.eventId}'">
                            <div class="reminder-icon">
                                <i class="fa-solid ${icon}"></i>
                            </div>
                            <div class="reminder-content">
                                <div class="reminder-time">${timeAgo}</div>
                                <div class="reminder-title">${reminder.eventTitle}</div>
                            </div>
                            <button class="btn btn-sm btn-link text-muted" onclick="eventReminderSystem.removeReminder(${reminder.id}); event.stopPropagation();" style="padding: 0;">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>
                    `;
                }).join('');

                this.upcomingRemindersContainer.innerHTML = remindersHTML;
            }

            getTimeAgo(date) {
                const now = new Date();
                const diff = date - now;
                const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

                if (days > 0) return `In ${days} day${days > 1 ? 's' : ''}`;
                if (hours > 0) return `In ${hours} hour${hours > 1 ? 's' : ''}`;
                if (minutes > 0) return `In ${minutes} minute${minutes > 1 ? 's' : ''}`;
                return 'Now';
            }

            getReminderIcon(date) {
                const now = new Date();
                const diff = date - now;
                const hours = diff / (1000 * 60 * 60);

                if (hours <= 1) return 'bi-exclamation-triangle-fill';
                if (hours <= 24) return 'bi-clock-fill';
                if (hours <= 72) return 'bi-calendar-event-fill';
                return 'bi-bell-fill';
            }

            checkPendingReminders() {
                const now = new Date();
                
                this.reminders.forEach(reminder => {
                    if (!reminder.notified && new Date(reminder.reminderTime) <= now) {
                        this.triggerReminder(reminder);
                        reminder.notified = true;
                    }
                });
                
                this.saveReminders();
                this.renderUpcomingReminders();
            }

            checkVeryShortReminders() {
                const now = new Date();
                const fiveSecondsFromNow = new Date(now.getTime() + 5000);
                
                this.reminders.forEach(reminder => {
                    if (!reminder.notified) {
                        const reminderTime = new Date(reminder.reminderTime);
                        
                        // Only check reminders that are within 5 seconds from now
                        // This is for very short intervals like 1 second
                        if (reminderTime >= now && reminderTime <= fiveSecondsFromNow) {
                            // Calculate milliseconds until reminder
                            const msUntilReminder = reminderTime.getTime() - now.getTime();
                            
                            if (msUntilReminder <= 1000) {
                                // Trigger immediately if within 1 second
                                this.triggerReminder(reminder);
                                reminder.notified = true;
                                this.saveReminders();
                                this.renderUpcomingReminders();
                            }
                        }
                    }
                });
            }

            triggerReminder(reminder) {
                console.log('🔔 TRIGGERING REMINDER:', reminder);
                
                const eventDate = new Date(reminder.eventDate);
                const reminderDate = new Date(reminder.reminderTime);
                const message = `Reminder: ${reminder.eventTitle} is scheduled for ${eventDate.toLocaleDateString()} at ${eventDate.toLocaleTimeString()}`;
                
                // Enhanced browser notification
                if (Notification.permission === 'granted') {
                    const notification = new Notification('🔔 Event Reminder', {
                        body: message,
                        icon: '/assets/img/favicon.ico',
                        tag: reminder.id.toString(),
                        requireInteraction: true, // Keep notification visible until user interacts
                        badge: '/assets/img/favicon.ico'
                    });
                    
                    // Auto-close after 10 seconds
                    setTimeout(() => {
                        notification.close();
                    }, 10000);
                    
                    // Click handler to go to event details
                    notification.onclick = function() {
                        window.location.href = `user_account/event-details.php?id=${reminder.eventId}`;
                    };
                }

                // Enhanced in-app notification
                this.showNotification('🔔 Event Reminder', message, 'warning', 15000);

                // Play sound (if available)
                this.playNotificationSound();
                
                // Log to console for debugging
                console.log(`✅ Reminder triggered: "${reminder.eventTitle}" at ${new Date().toLocaleString()}`);
            }

            showNotification(title, message, type = 'info', duration = 5000) {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.innerHTML = `
                    <div class="notification-content">
                        <div class="notification-title">${title}</div>
                        <div class="notification-message">${message}</div>
                    </div>
                    <button class="notification-close" onclick="this.parentElement.remove()">
                        <i class="fa-solid fa-times"></i>
                    </button>
                `;

                // Add to page
                document.body.appendChild(notification);

                // Auto remove after duration
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, duration);
            }

            // Debug method to show all scheduled reminders
            debugReminders() {
                console.log('📅 ALL SCHEDULED REMINDERS:');
                this.reminders.forEach((reminder, index) => {
                    const reminderTime = new Date(reminder.reminderTime);
                    const now = new Date();
                    const msUntil = reminderTime.getTime() - now.getTime();
                    const secondsUntil = Math.round(msUntil / 1000);
                    const status = reminder.notified ? '✅ SENT' : (msUntil > 0 ? '⏳ PENDING' : '⏰ OVERDUE');
                    
                    console.log(`${index + 1}. ${reminder.eventTitle}`);
                    console.log(`   📅 Event: ${new Date(reminder.eventDate).toLocaleString()}`);
                    console.log(`   ⏰ Reminder: ${reminderTime.toLocaleString()}`);
                    console.log(`   ⏱️  Time until: ${secondsUntil} seconds`);
                    console.log(`   📊 Status: ${status}`);
                    console.log('   ---');
                });
                
                // Show summary notification
                const pendingCount = this.reminders.filter(r => !r.notified).length;
                const overdueCount = this.reminders.filter(r => !r.notified && new Date(r.reminderTime) < new Date()).length;
                
                this.showNotification('Debug Info', `Total: ${this.reminders.length}, Pending: ${pendingCount}, Overdue: ${overdueCount}`, 'info', 10000);
            }

            playNotificationSound() {
                try {
                    const audio = new Audio('/assets/sounds/notification.mp3');
                    audio.volume = 0.3;
                    audio.play().catch(() => {
                        // Ignore audio play errors
                    });
                } catch (error) {
                    // Ignore audio errors
                }
            }

            async requestNotificationPermission() {
                if ('Notification' in window && Notification.permission === 'default') {
                    const permission = await Notification.requestPermission();
                    if (permission === 'granted') {
                        this.showNotification('Notifications Enabled', 'You will receive browser notifications for event reminders', 'success');
                    }
                }
            }

            createReminderForEvent(event) {
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Set Event Reminder</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Event</label>
                                    <input type="text" class="form-control" value="${event.title}" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Event Date</label>
                                    <input type="text" class="form-control" value="${new Date(event.event_date).toLocaleDateString()}" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remind me</label>
                                    <select class="form-select" id="reminderTime">
                                        <option value="">Select reminder time</option>
                                        <option value="10">10 seconds before</option>
                                        <option value="5">5 minutes before</option>
                                        <option value="15">15 minutes before</option>
                                        <option value="30">30 minutes before</option>
                                        <option value="60">1 hour before</option>
                                        <option value="1440">1 day before</option>
                                        <option value="2880">2 days before</option>
                                        <option value="10080">1 week before</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="eventReminderSystem.saveReminderFromModal(${event.id}, '${event.title.replace(/'/g, "\\'")}', '${event.event_date}')">
                                    Set Reminder
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
                
                modal.addEventListener('hidden.bs.modal', () => {
                    document.body.removeChild(modal);
                });
            }

            saveReminderFromModal(eventId, eventTitle, eventDate) {
                const reminderSelect = document.getElementById('reminderTime');
                const minutesBefore = parseInt(reminderSelect.value);
                
                if (!minutesBefore) {
                    alert('Please select a reminder time');
                    return;
                }
                
                const eventDateTime = new Date(eventDate);
                const reminderTime = new Date(eventDateTime.getTime() - (minutesBefore * 60 * 1000));
                
                // Add debugging information for testing
                console.log('Event Date:', eventDateTime);
                console.log('Minutes Before:', minutesBefore);
                console.log('Reminder Time:', reminderTime);
                console.log('Current Time:', new Date());
                console.log('Time Until Reminder:', reminderTime.getTime() - new Date().getTime(), 'ms');
                
                // Check if reminder time is in the past (for testing with old events)
                const now = new Date();
                if (reminderTime < now) {
                    const confirmPast = confirm(`This reminder is scheduled for ${reminderTime.toLocaleString()}, which is in the past. Do you want to set it anyway for testing?`);
                    if (!confirmPast) {
                        return;
                    }
                }
                
                this.addReminder(eventId, eventTitle, eventDate, reminderTime.toISOString());
                
                // Show confirmation with exact time
                const timeUntilReminder = reminderTime.getTime() - now.getTime();
                const timeUntilText = timeUntilReminder > 0 
                    ? `Reminder set for ${reminderTime.toLocaleString()} (in ${Math.round(timeUntilReminder / 1000)} seconds)`
                    : `Reminder set for ${reminderTime.toLocaleString()} (in the past - testing mode)`;
                
                this.showNotification('Reminder Set', timeUntilText, 'info', 8000);
                
                // Close modal
                const modal = reminderSelect.closest('.modal');
                bootstrap.Modal.getInstance(modal).hide();
            }
        }

        function showReminderSettings() {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reminder Settings</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="browserNotifications" ${Notification.permission === 'granted' ? 'checked' : ''}>
                                    <label class="form-check-label" for="browserNotifications">
                                        Enable browser notifications
                                    </label>
                                </div>
                                <small class="text-muted">Receive notifications even when the browser is closed</small>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="soundNotifications" checked>
                                    <label class="form-check-label" for="soundNotifications">
                                        Play notification sound
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Default reminder time</label>
                                <select class="form-select">
                                    <option value="30">30 minutes before</option>
                                    <option value="60">1 hour before</option>
                                    <option value="1440">1 day before</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email Notifications
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-at"></i></span>
                                    <input type="email" class="form-control" id="emailNotification" placeholder="Enter your Gmail address for event and news notifications">
                                    <button class="btn btn-outline-primary" type="button" id="saveEmailBtn">
                                        <i class="fas fa-save me-1"></i>Save
                                    </button>
                                </div>
                                <small class="text-muted">Get notified about upcoming events and news announcements via email</small>
                                <div id="emailStatus" class="mt-2"></div>
                            </div>
                            <div class="text-center">
                                <button class="btn btn-outline-danger btn-sm" onclick="eventReminderSystem.clearAllReminders()">
                                    <i class="fa-solid fa-trash"></i> Clear All Reminders
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            // Add email save functionality
            const saveEmailBtn = modal.querySelector('#saveEmailBtn');
            const emailInput = modal.querySelector('#emailNotification');
            const emailStatus = modal.querySelector('#emailStatus');
            
            saveEmailBtn.addEventListener('click', async () => {
                const email = emailInput.value.trim();
                if (!email) {
                    emailStatus.innerHTML = '<div class="alert alert-warning py-1">Please enter an email address</div>';
                    return;
                }
                
                saveEmailBtn.disabled = true;
                saveEmailBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
                
                try {
                    const response = await fetch('api/email_subscription_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ email: email, action: 'subscribe' })
                    });
                    
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        emailStatus.innerHTML = `<div class="alert alert-success py-1"><i class="fas fa-check-circle me-1"></i>${result.message}</div>`;
                        emailInput.value = '';
                    } else if (result.status === 'info') {
                        emailStatus.innerHTML = `<div class="alert alert-info py-1"><i class="fas fa-info-circle me-1"></i>${result.message}</div>`;
                        emailInput.value = '';
                    } else {
                        emailStatus.innerHTML = `<div class="alert alert-danger py-1"><i class="fas fa-exclamation-circle me-1"></i>${result.message}</div>`;
                    }
                } catch (error) {
                    emailStatus.innerHTML = '<div class="alert alert-danger py-1">Error saving email. Please try again.</div>';
                } finally {
                    saveEmailBtn.disabled = false;
                    saveEmailBtn.innerHTML = '<i class="fas fa-save me-1"></i>Save';
                }
            });
            
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        }

        // ============================================================
        //  EVENT ANALYTICS SYSTEM
        // ============================================================
        class EventAnalyticsSystem {
            constructor() {
                this.events = [];
                this.chart = null;
                this.init();
            }

            init() {
                // Load events when search filter is ready
                setTimeout(() => {
                    this.events = window.eventSearchFilter?.allEvents || [];
                    this.updateAnalytics();
                }, 1000);
            }

            updateAnalytics() {
                this.updateSummaryCards();
                this.renderChart();
            }

            updateSummaryCards() {
                const totalEvents = this.events.length;
                const today = new Date();
                const upcomingEvents = this.events.filter(event => new Date(event.event_date) >= today).length;
                const officialEvents = this.events.filter(event => event.is_official).length;

                document.getElementById('totalEventsCount').textContent = totalEvents;
                document.getElementById('upcomingEventsCount').textContent = upcomingEvents;
                document.getElementById('officialEventsCount').textContent = officialEvents;
            }

            renderChart() {
                const canvas = document.getElementById('eventsChart');
                if (!canvas) return;

                const ctx = canvas.getContext('2d');
                const categoryData = this.getCategoryData();

                // Simple bar chart using canvas
                this.drawBarChart(ctx, categoryData);
            }

            getCategoryData() {
                const categories = {};
                
                this.events.forEach(event => {
                    categories[event.category] = (categories[event.category] || 0) + 1;
                });

                return Object.entries(categories)
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 5); // Top 5 categories
            }

            drawBarChart(ctx, data) {
                const canvas = ctx.canvas;
                const width = canvas.width;
                const height = canvas.height;
                const padding = 20;
                const barWidth = (width - padding * 2) / data.length;
                const maxValue = Math.max(...data.map(d => d[1]));

                // Clear canvas
                ctx.clearRect(0, 0, width, height);

                // Draw bars
                data.forEach(([category, count], index) => {
                    const barHeight = (count / maxValue) * (height - padding * 2);
                    const x = padding + index * barWidth;
                    const y = height - padding - barHeight;

                    // Bar color based on category
                    const colors = {
                        'Academic Calendar': '#1abc9c',
                        'Holidays': '#e74c3c',
                        'Assessments': '#f39c12',
                        'Professional Development': '#3498db',
                        'Academic': '#16a085'
                    };
                    
                    ctx.fillStyle = colors[category] || '#95a5a6';
                    ctx.fillRect(x + 2, y, barWidth - 4, barHeight);

                    // Draw value on top
                    ctx.fillStyle = '#666';
                    ctx.font = '10px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText(count, x + barWidth / 2, y - 2);
                });
            }

            generateDetailedReport() {
                const report = {
                    summary: this.generateSummary(),
                    categories: this.generateCategoryBreakdown(),
                    timeline: this.generateTimelineAnalysis(),
                    officialEvents: this.generateOfficialEventsReport()
                };

                return report;
            }

            generateSummary() {
                const totalEvents = this.events.length;
                const today = new Date();
                const upcomingEvents = this.events.filter(event => new Date(event.event_date) >= today).length;
                const pastEvents = this.events.filter(event => new Date(event.event_date) < today).length;
                const officialEvents = this.events.filter(event => event.is_official).length;
                const multiDayEvents = this.events.filter(event => event.event_days > 1).length;

                return {
                    totalEvents,
                    upcomingEvents,
                    pastEvents,
                    officialEvents,
                    multiDayEvents,
                    officialPercentage: ((officialEvents / totalEvents) * 100).toFixed(1),
                    multiDayPercentage: ((multiDayEvents / totalEvents) * 100).toFixed(1)
                };
            }

            generateCategoryBreakdown() {
                const categories = {};
                
                this.events.forEach(event => {
                    if (!categories[event.category]) {
                        categories[event.category] = {
                            count: 0,
                            official: 0,
                            upcoming: 0,
                            multiDay: 0
                        };
                    }
                    
                    categories[event.category].count++;
                    if (event.is_official) categories[event.category].official++;
                    if (new Date(event.event_date) >= new Date()) categories[event.category].upcoming++;
                    if (event.event_days > 1) categories[event.category].multiDay++;
                });

                return categories;
            }

            generateTimelineAnalysis() {
                const monthlyData = {};
                const today = new Date();
                const sixMonthsAgo = new Date(today.getFullYear(), today.getMonth() - 6, 1);

                this.events
                    .filter(event => new Date(event.event_date) >= sixMonthsAgo)
                    .forEach(event => {
                        const date = new Date(event.event_date);
                        const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                        
                        if (!monthlyData[monthKey]) {
                            monthlyData[monthKey] = { total: 0, official: 0 };
                        }
                        
                        monthlyData[monthKey].total++;
                        if (event.is_official) monthlyData[monthKey].official++;
                    });

                return monthlyData;
            }

            generateOfficialEventsReport() {
                const officialEvents = this.events.filter(event => event.is_official);
                const sourceBreakdown = {};
                
                officialEvents.forEach(event => {
                    const source = event.source || 'Unknown';
                    sourceBreakdown[source] = (sourceBreakdown[source] || 0) + 1;
                });

                return {
                    total: officialEvents.length,
                    sources: sourceBreakdown,
                    categories: this.getOfficialCategories(officialEvents)
                };
            }

            getOfficialCategories(officialEvents) {
                const categories = {};
                officialEvents.forEach(event => {
                    categories[event.category] = (categories[event.category] || 0) + 1;
                });
                return categories;
            }
        }

        function showAnalyticsModal() {
            const analytics = window.eventAnalyticsSystem;
            const report = analytics.generateDetailedReport();

            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Event Analytics Dashboard</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Summary Statistics</h6>
                                    <div class="analytics-report">
                                        <div class="stat-item">
                                            <span class="stat-label">Total Events:</span>
                                            <span class="stat-value">${report.summary.totalEvents}</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Upcoming Events:</span>
                                            <span class="stat-value">${report.summary.upcomingEvents}</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Official Events:</span>
                                            <span class="stat-value">${report.summary.officialEvents} (${report.summary.officialPercentage}%)</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Multi-Day Events:</span>
                                            <span class="stat-value">${report.summary.multiDayEvents} (${report.summary.multiDayPercentage}%)</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Category Breakdown</h6>
                                    <div class="category-breakdown">
                                        ${Object.entries(report.categories)
                                            .sort((a, b) => b[1].count - a[1].count)
                                            .slice(0, 5)
                                            .map(([category, data]) => `
                                                <div class="category-stat">
                                                    <span class="category-name">${category}</span>
                                                    <span class="category-count">${data.count}</span>
                                                </div>
                                            `).join('')}
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Official Events Sources</h6>
                                    <div class="sources-breakdown">
                                        ${Object.entries(report.officialEvents.sources)
                                            .map(([source, count]) => `
                                                <div class="source-stat">
                                                    <span class="source-name">${source}</span>
                                                    <span class="source-count">${count}</span>
                                                </div>
                                            `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="exportAnalyticsReport()">
                                <i class="fa-solid fa-download"></i> Export Report
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        }

        function exportAnalyticsReport() {
            const analytics = window.eventAnalyticsSystem;
            const report = analytics.generateDetailedReport();
            
            const reportHTML = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Event Analytics Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .section { margin-bottom: 30px; }
                        .stat-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
                        .category-stat, .source-stat { display: flex; justify-content: space-between; padding: 6px 0; }
                        h2 { color: #1abc9c; }
                        h3 { color: #2c3e50; border-bottom: 2px solid #1abc9c; padding-bottom: 5px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h2>BUNHS Event Analytics Report</h2>
                        <p>Generated on ${new Date().toLocaleDateString()}</p>
                    </div>
                    
                    <div class="section">
                        <h3>Summary Statistics</h3>
                        <div class="stat-item"><span>Total Events:</span><span>${report.summary.totalEvents}</span></div>
                        <div class="stat-item"><span>Upcoming Events:</span><span>${report.summary.upcomingEvents}</span></div>
                        <div class="stat-item"><span>Past Events:</span><span>${report.summary.pastEvents}</span></div>
                        <div class="stat-item"><span>Official Events:</span><span>${report.summary.officialEvents} (${report.summary.officialPercentage}%)</span></div>
                        <div class="stat-item"><span>Multi-Day Events:</span><span>${report.summary.multiDayEvents} (${report.summary.multiDayPercentage}%)</span></div>
                    </div>
                    
                    <div class="section">
                        <h3>Category Breakdown</h3>
                        ${Object.entries(report.categories)
                            .sort((a, b) => b[1].count - a[1].count)
                            .map(([category, data]) => `
                                <div class="category-stat">
                                    <span>${category}</span>
                                    <span>${data.count} (${data.official} official, ${data.upcoming} upcoming)</span>
                                </div>
                            `).join('')}
                    </div>
                    
                    <div class="section">
                        <h3>Official Events Sources</h3>
                        ${Object.entries(report.officialEvents.sources)
                            .map(([source, count]) => `
                                <div class="source-stat">
                                    <span>${source}</span>
                                    <span>${count}</span>
                                </div>
                            `).join('')}
                    </div>
                </body>
                </html>
            `;
            
            const blob = new Blob([reportHTML], { type: 'text/html' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `event-analytics-report-${new Date().toISOString().split('T')[0]}.html`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            // Close modal
            const modal = document.querySelector('.modal.show');
            if (modal) {
                bootstrap.Modal.getInstance(modal).hide();
            }
        }

        // ============================================================
        //  DYNAMIC CONTENT LOADING & TIMEZONE HANDLING
        // ============================================================
        
        // Set timezone to Asia/Manila
        function setupPhilippineTimezone() {
            // This would ideally be set server-side, but we'll handle it client-side
            return {
                timezone: 'Asia/Manila',
                formatTime: function(date) {
                    return new Intl.DateTimeFormat('en-PH', {
                        timeZone: this.timezone,
                        hour: 'numeric',
                        minute: '2-digit',
                        hour12: true
                    }).format(date);
                },
                formatDate: function(date) {
                    return new Intl.DateTimeFormat('en-PH', {
                        timeZone: this.timezone,
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    }).format(date);
                }
            };
        }

        const phTime = setupPhilippineTimezone();

        // Dynamic content loading for sidebar items
        function loadSidebarContent(type, params = {}) {
            const contentContainer = document.getElementById(`${type}Content`);
            if (!contentContainer) return;

            // Prevent infinite loops with debouncing
            const now = Date.now();
            const lastLoadTime = contentContainer.dataset.lastLoadTime || 0;
            if (now - lastLoadTime >= 1000) { // Only allow if 1 second has passed
                contentContainer.dataset.lastLoadTime = now;
                
                // Show loading state
                contentContainer.innerHTML = `
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading ${type}...</p>
                    </div>
                `;

                // Fetch content via AJAX
                fetch('ajax_handlers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: `get_${type}`,
                        ...params
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderSidebarContent(type, data);
                    } else {
                        contentContainer.innerHTML = `<p class="text-danger">Error loading ${type}: ${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error loading', type, ':', error);
                    contentContainer.innerHTML = `<p class="text-danger">Network error loading ${type}</p>`;
                });
            } else {
                console.log('Debouncing AJAX call for', type, '- too soon');
            }
        }

        function renderSidebarContent(type, data) {
            const contentContainer = document.getElementById(`${type}Content`);
            if (!contentContainer) return;

            let html = '';
            
            switch(type) {
                case 'events':
                    html = renderEventsList(data.events || []);
                    break;
                case 'announcements':
                    html = renderAnnouncementsList(data.announcements || []);
                    break;
                case 'featured':
                    html = renderFeaturedEvent(data.featured_event);
                    break;
                default:
                    html = '<p>No content available</p>';
            }
            
            contentContainer.innerHTML = html;
        }

        function renderEventsList(events) {
            if (events.length === 0) {
                return '<p class="text-muted">No events found</p>';
            }

            const eventsHtml = events.map(event => {
                const eventDate = new Date(event.event_date);
                const timeStr = event.event_start_time ? 
                    phTime.formatTime(new Date(`${event.event_date}T${event.event_start_time}`)) : 
                    'All day';
                
                return `
                    <div class="sidebar-event-item" onclick="viewEventDetails(${event.id})">
                        <div class="event-time">${timeStr}</div>
                        <div class="event-title">${event.title}</div>
                        <div class="event-meta">
                            <span class="event-category" style="background: ${getCategoryColor(event.category)}">
                                ${event.category}
                            </span>
                            ${event.is_official ? '<i class="fa-solid fa-certificate text-primary" title="Official Event"></i>' : ''}
                        </div>
                    </div>
                `;
            }).join('');

            return `<div class="sidebar-events-list">${eventsHtml}</div>`;
        }

        function renderAnnouncementsList(announcements) {
            if (announcements.length === 0) {
                return '<p class="text-muted">No announcements found</p>';
            }

            const announcementsHtml = announcements.map(announcement => `
                <div class="sidebar-announcement-item" onclick="viewAnnouncementDetails(${announcement.id})">
                    <div class="announcement-title">${announcement.title}</div>
                    <div class="announcement-date">${phTime.formatDate(new Date(announcement.created_at))}</div>
                </div>
            `).join('');

            return `<div class="sidebar-announcements-list">${announcementsHtml}</div>`;
        }

        function renderFeaturedEvent(featuredEvent) {
            if (!featuredEvent) {
                return '<p class="text-muted">No featured event</p>';
            }

            const eventDate = new Date(featuredEvent.event_date);
            return `
                <div class="featured-event-card" onclick="viewEventDetails(${featuredEvent.id})">
                    <div class="featured-event-header">
                        <h4>${featuredEvent.title}</h4>
                        ${featuredEvent.is_official ? '<span class="official-badge"><i class="fa-solid fa-certificate"></i> Official</span>' : ''}
                    </div>
                    <div class="featured-event-body">
                        <p class="featured-event-date">
                            <i class="fa-solid fa-calendar-days"></i> 
                            ${phTime.formatDate(eventDate)}
                        </p>
                        ${featuredEvent.description ? `<p class="featured-event-description">${featuredEvent.description.substring(0, 100)}...</p>` : ''}
                    </div>
                </div>
            `;
        }

        function getCategoryColor(category) {
            const colors = {
                'Academic': '#1abc9c',
                'Sports': '#e74c3c',
                'Cultural': '#9b59b6',
                'Workshops': '#3498db',
                'Conferences': '#2c3e50',
                'Academic Calendar': '#16a085',
                'Holidays': '#c0392b',
                'Health & Nutrition': '#27ae60',
                'Governance & Elections': '#8e44ad',
                'Assessments': '#f39c12',
                'Professional Development': '#2980b9',
                'Remedial & Intervention': '#d35400'
            };
            return colors[category] || '#95a5a6';
        }

        function viewEventDetails(eventId) {
            window.location.href = `user_account/event-details.php?id=${eventId}`;
        }

        function viewAnnouncementDetails(announcementId) {
            window.location.href = `admin_account/announcements/view_announcement.php?id=${announcementId}`;
        }

        // Enhanced calendar view switching with dynamic content loading
        function switchCalendarView(view) {
            currentView = view;
            
            // Update button states
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-view="${view}"]`).classList.add('active');
            
            // Show loading state
            const calendarContainer = document.getElementById('calendarContainer');
            if (calendarContainer) {
                calendarContainer.innerHTML = `
                    <div class="calendar-loading">
                        <div class="loading-spinner"></div>
                        <p>Loading ${view} view...</p>
                    </div>
                `;
            }
            
            // Load view content dynamically
            setTimeout(() => {
                switch(view) {
                    case 'month':
                        renderMonthView();
                        break;
                    case 'week':
                        renderWeekView();
                        break;
                    case 'day':
                        renderDayView();
                        break;
                }
                updateCalendarHeader();
            }, 300);
        }

        // ============================================================

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize advanced search and filtering
            window.eventSearchFilter = new EventSearchFilter();
            
            // Initialize reminder system
            window.eventReminderSystem = new EventReminderSystem();
            
            // Initialize analytics system
            window.eventAnalyticsSystem = new EventAnalyticsSystem();
            
            loadUpcomingEvents();
            
            // Add filtering functionality
            const officialCheckbox = document.getElementById('officialOnly');
            const categoryLinks = document.querySelectorAll('.category-link');
            
            function updateFilters() {
                const category = getCurrentCategory();
                const official = officialCheckbox.checked;
                
                // Update EventSearchFilter if it exists
                if (window.eventSearchFilter) {
                    // Update the EventSearchFilter's category filter
                    window.eventSearchFilter.filters.categories = category === 'all' ? [] : [category];
                    window.eventSearchFilter.filters.officialOnly = official;
                    window.eventSearchFilter.applyFilters();
                } else {
                    // Fallback to our original system
                    loadFilteredEvents(category, official);
                }
            }
            
            function getCurrentCategory() {
                const activeLink = document.querySelector('.category-link.active');
                return activeLink ? activeLink.dataset.category : 'all';
            }
            
            // Category link clicks
            categoryLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links
                    categoryLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    updateFilters();
                });
            });
            
            // Official checkbox change
            if (officialCheckbox) {
                officialCheckbox.addEventListener('change', updateFilters);
            }
        });
    </script>

    <!-- Event Modal -->
    <div class="modal fade event-modal" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="event-date-header">
                    <h5 class="modal-title" id="eventModalLabel">Events on this date</h5>
                    <div class="event-date-display" id="eventDateDisplay"></div>
                    <div class="modal-body">
                        <div class="events-list-modal" id="eventsListForDate"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>

                    <!-- Calendar JavaScript -->
                    <script>
                        let currentMonth = new Date().getMonth();
                        let currentYear = new Date().getFullYear();
                        let eventsData = <?php echo $eventsDataJson; ?>;
                        let currentView = 'month';
                        let currentDate = new Date();
                        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                        const dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

                        // Calendar View Switching
                        function switchCalendarView(view) {
                            currentView = view;
                            
                            // Update button states
                            document.querySelectorAll('.view-btn').forEach(btn => {
                                btn.classList.remove('active');
                            });
                            document.querySelector(`[data-view="${view}"]`).classList.add('active');
                            
                            // Hide all views
                            document.getElementById('calendarDays').parentElement.style.display = 'none';
                            document.getElementById('calendarWeekView').style.display = 'none';
                            document.getElementById('calendarDayView').style.display = 'none';
                            
                            // Show selected view
                            switch(view) {
                                case 'month':
                                    document.getElementById('calendarDays').parentElement.style.display = 'block';
                                    renderCalendar(currentYear, currentMonth);
                                    break;
                                case 'week':
                                    document.getElementById('calendarWeekView').style.display = 'block';
                                    renderWeekView();
                                    break;
                                case 'day':
                                    document.getElementById('calendarDayView').style.display = 'block';
                                    renderDayView();
                                    break;
                            }
                        }

                        function renderWeekView() {
                            const weekGrid = document.getElementById('weekGrid');
                            if (!weekGrid) return;
                            
                            const today = new Date();
                            const startOfWeek = new Date(today);
                            startOfWeek.setDate(today.getDate() - today.getDay());
                            
                            let html = '<div class="week-time-slot">Time</div>';
                            
                            // Add day headers (original design)
                            for (let i = 0; i < 7; i++) {
                                const date = new Date(startOfWeek);
                                date.setDate(startOfWeek.getDate() + i);
                                const isToday = isSameDay(date, today);
                                html += `
                                    <div class="week-day-header">
                                        ${dayNames[i].substring(0, 3)}<br>
                                        <small>${date.getMonth() + 1}/${date.getDate()}</small>
                                        ${isToday ? '<br><small class="text-primary">Today</small>' : ''}
                                    </div>
                                `;
                            }
                            
                            // Add time slots (original design with Philippine time)
                            for (let hour = 8; hour <= 17; hour++) {
                                const hour12 = hour === 0 ? 12 : (hour > 12 ? hour - 12 : hour);
                                const ampm = hour >= 12 ? 'PM' : 'AM';
                                const timeStr = `${hour12}:00 ${ampm}`;
                                
                                html += `<div class="week-time-slot">${timeStr}</div>`;
                                
                                for (let day = 0; day < 7; day++) {
                                    const date = new Date(startOfWeek);
                                    date.setDate(startOfWeek.getDate() + day);
                                    const dateStr = formatDateForComparison(date);
                                    const dayEvents = getEventsForDate(dateStr);
                                    
                                    html += '<div class="week-day-cell">';
                                    dayEvents.forEach(event => {
                                        const eventHour = parseInt(event.event_start_time?.split(':')[0] || 12);
                                        if (eventHour === hour) {
                                            html += `
                                                <div class="week-event" onclick="window.location.href='user_account/event-details.php?id=${event.id}'">
                                                    ${phTime.formatTime(new Date(`${event.event_date}T${event.event_start_time}`))} ${event.title}
                                                </div>
                                            `;
                                        }
                                    });
                                    html += '</div>';
                                }
                            }
                            
                            weekGrid.innerHTML = html;
                        }

                        function renderDayView() {
                            const dayTimeline = document.getElementById('dayTimeline');
                            const dateStr = formatDateForComparison(currentDate);
                            const dayEvents = getEventsForDate(dateStr);
                            
                            let html = '';
                            
                            for (let hour = 8; hour <= 17; hour++) {
                                const hourEvents = dayEvents.filter(event => {
                                    const eventHour = parseInt(event.event_start_time?.split(':')[0] || 12);
                                    return eventHour === hour;
                                });
                                
                                html += `
                                    <div class="day-time-slot">
                                        <div class="day-time-label">${hour}:00</div>
                                        <div class="day-events-container">
                                `;
                                
                                hourEvents.forEach(event => {
                                    html += `
                                        <div class="day-event-block" onclick="window.location.href='user_account/event-details.php?id=${event.id}'">
                                            <div class="day-event-time">${event.event_start_time || 'All Day'}</div>
                                            <div class="day-event-title">${event.title}</div>
                                        </div>
                                    `;
                                });
                                
                                html += `
                                        </div>
                                    </div>
                                `;
                            }
                            
                            dayTimeline.innerHTML = html;
                        }

                        function getEventsForDate(dateStr) {
                            // First try to get from current eventsData
                            let events = eventsData[dateStr] || [];
                            
                            // If no events found for this date, try to load them dynamically
                            if (events.length === 0) {
                                // Load events for this date via AJAX
                                fetch('ajax_handlers.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: new URLSearchParams({
                                        action: 'get_events',
                                        criteria: JSON.stringify({
                                            date_from: dateStr,
                                            date_to: dateStr
                                        })
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success' && data.events) {
                                        // Update eventsData with loaded events
                                        if (!eventsData[dateStr]) {
                                            eventsData[dateStr] = [];
                                        }
                                        data.events.forEach(event => {
                                            eventsData[dateStr].push(event);
                                        });
                                        
                                        // Re-render the week view to show loaded events
                                        if (currentView === 'week') {
                                            renderWeekView();
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading events for date:', dateStr, error);
                                });
                            }
                            
                            return events;
                        }

                        function formatDateForComparison(date) {
                            const year = date.getFullYear();
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const day = String(date.getDate()).padStart(2, '0');
                            return `${year}-${month}-${day}`;
                        }

                        function isSameDay(date1, date2) {
                            return date1.getFullYear() === date2.getFullYear() &&
                                   date1.getMonth() === date2.getMonth() &&
                                   date1.getDate() === date2.getDate();
                        }

                        // Enhanced navigation for different views
                        function navigateCalendar(direction) {
                            switch(currentView) {
                                case 'month':
                                    changeMonth(direction);
                                    break;
                                case 'week':
                                    navigateWeek(direction);
                                    break;
                                case 'day':
                                    navigateDay(direction);
                                    break;
                            }
                        }

                        function navigateWeek(direction) {
                            currentDate.setDate(currentDate.getDate() + (direction * 7));
                            renderWeekView();
                            updateCalendarHeader();
                        }

                        function navigateDay(direction) {
                            currentDate.setDate(currentDate.getDate() + direction);
                            renderDayView();
                            updateCalendarHeader();
                        }

                        function updateCalendarHeader() {
                            const header = document.getElementById('monthYearDisplay');
                            if (!header) return;
                            
                            switch(currentView) {
                                case 'month':
                                    header.innerHTML = `${monthNames[currentMonth]}<br><span style="font-size:18px">${currentYear}</span>`;
                                    break;
                                case 'week':
                                    const weekStart = new Date(currentDate);
                                    const day = weekStart.getDay();
                            weekStart.setDate(weekStart.getDate() - day);
                            const weekEnd = new Date(weekStart);
                            weekEnd.setDate(weekStart.getDate() + 6);
                            
                            header.innerHTML = `
                                ${weekStart.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - 
                                ${weekEnd.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                            `;
                            break;
                        case 'day':
                            header.innerHTML = `
                                ${currentDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}
                            `;
                            break;
                    }
                }

                        function renderCalendar(year, month) {
                            const monthYearDisplay = document.getElementById('monthYearDisplay');
                            const calendarDays = document.getElementById('calendarDays');
                            monthYearDisplay.innerHTML = monthNames[month] + '<br><span style="font-size:18px">' + year + '</span>';
                            const firstDay = new Date(year, month, 1).getDay();
                            const daysInMonth = new Date(year, month + 1, 0).getDate();
                            const daysInPrevMonth = new Date(year, month, 0).getDate();
                            const today = new Date();
                            let html = '';
                            for (let i = firstDay - 1; i >= 0; i--) {
                                html += '<li class="other-month">' + (daysInPrevMonth - i) + '</li>';
                            }
                            for (let i = 1; i <= daysInMonth; i++) {
                                const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(i).padStart(2, '0');
                                const isToday = (year === today.getFullYear() && month === today.getMonth() && i === today.getDate());
                                const hasEvents = eventsData[dateStr] && eventsData[dateStr].length > 0;
                                let classes = isToday ? ' today' : '';
                                let eventDots = '';
                                let eventCategory = '';
                                
                                if (hasEvents) {
                                    classes += ' has-event';
                                    
                                    // Determine primary category for styling
                                    if (eventsData[dateStr].length > 1) {
                                        classes += ' multiple-events';
                                    } else {
                                        eventCategory = eventsData[dateStr][0].category.toLowerCase();
                                        classes += ' ' + eventCategory;
                                    }
                                    
                                    // Add event dots for each event
                                    eventsData[dateStr].forEach(event => {
                                        const categoryClass = event.category.toLowerCase().replace(/\s+/g, '-');
                                        eventDots += '<span class="event-dot ' + categoryClass + '"></span>';
                                    });
                                }
                                
                                html += '<li' + classes + ' onclick="openEventModal(\'' + dateStr + '\')">' + i + eventDots + '</li>';
                            }
                            const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;
                            const remainingDays = totalCells - (firstDay + daysInMonth);
                            for (let i = 1; i <= remainingDays; i++) {
                                html += '<li class="other-month">' + i + '</li>';
                            }
                            calendarDays.innerHTML = html;
                        }

                        function loadEventsForMonth(year, month) {
                            // Prevent duplicate requests
                            if (window.currentLoadingMonth === `${year}-${month}`) {
                                console.log('Already loading events for', year, month);
                                return;
                            }
                            window.currentLoadingMonth = `${year}-${month}`;
                            
                            fetch(ANNOUNCEMENTS_URL, {
                                    method: 'POST',
                                    body: new URLSearchParams({
                                        'action': 'get_events',
                                        'year': year,
                                        'month': month
                                    }),
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    }
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success') {
                                        eventsData = {};
                                        data.events.forEach(event => {
                                            const dateKey = event.event_date;
                                            if (!eventsData[dateKey]) eventsData[dateKey] = [];
                                            eventsData[dateKey].push(event);
                                        });
                                        renderCalendar(currentYear, currentMonth);
                                    }
                                })
                                .catch(error => console.error('Error loading events:', error));
                        }

                        function changeMonth(delta) {
                            currentMonth += delta;
                            if (currentMonth > 11) {
                                currentMonth = 0;
                                currentYear++;
                            } else if (currentMonth < 0) {
                                currentMonth = 11;
                                currentYear--;
                            }
                            loadEventsForMonth(currentYear, currentMonth + 1);
                        }

                        function openEventModal(dateStr) {
                            const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
                            const eventDateDisplay = document.getElementById('eventDateDisplay');
                            const date = new Date(dateStr);
                            const formattedDate = date.toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                            eventDateDisplay.textContent = formattedDate;
                            loadEventsForDateModal(dateStr);
                            eventModal.show();
                        }

                        function loadEventsForDateModal(dateStr) {
                            const eventsListContainer = document.getElementById('eventsListForDate');
                            if (eventsData[dateStr] && eventsData[dateStr].length > 0) {
                                let html = '';
                                eventsData[dateStr].forEach(event => {
                                    const categoryClass = event.category.toLowerCase();
                                    let eventTime = '';
                                    if (event.event_start_time || event.event_end_time) {
                                        const formatTime = (time) => {
                                            if (!time) return '';
                                            const [hours, minutes] = time.split(':');
                                            const h = parseInt(hours);
                                            return (h % 12 || 12) + ':' + minutes + ' ' + (h >= 12 ? 'PM' : 'AM');
                                        };
                                        const startTime = formatTime(event.event_start_time);
                                        const endTime = formatTime(event.event_end_time);
                                        eventTime = (startTime && endTime) ? startTime + ' - ' + endTime : (startTime || endTime);
                                    }
                                    html += '<div class="event-list-item category-' + categoryClass + '"><h6>' + event.title + '</h6><span class="event-item-category ' + categoryClass + '">' + event.category + '</span>' + (eventTime ? '<p class="mt-2"><i class="fa-solid fa-clock"></i> ' + eventTime + '</p>' : '') + (event.description ? '<p class="mt-2">' + event.description + '</p>' : '') + '</div>';
                                });
                                eventsListContainer.innerHTML = html;
                            } else {
                                eventsListContainer.innerHTML = '<p class="text-muted">No events on this date.</p>';
                            }
                        }

                        document.addEventListener('DOMContentLoaded', function() {
                            renderCalendar(currentYear, currentMonth);
                        });
                    </script>
</body>

</html>