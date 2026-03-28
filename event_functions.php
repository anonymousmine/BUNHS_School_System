<?php
// ============================================================
//  EVENT FUNCTIONS FOR AJAX HANDLERS
//  This file contains only the event functions needed by ajax_handlers.php
// ============================================================

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

// Function to get all events (enhanced version)
function get_all_events($conn)
{
    return getAllEventsEnhanced();
}
?>
