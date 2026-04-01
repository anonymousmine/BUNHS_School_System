<?php
// Database connection
include 'db_connection.php';

// Get article ID from request
$articleId = $_GET['id'] ?? 0;

if ($articleId > 0) {
    // Prepare and execute query to get complete article content
    $stmt = $conn->prepare("SELECT title, short_description, content, image, category, news_date, author FROM news WHERE id = ?");
    $stmt->bind_param("i", $articleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Return the complete article data as JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'title' => $row['title'],
            'short_description' => $row['short_description'],
            'content' => $row['content'], // This is the full content
            'image' => $row['image'],
            'category' => $row['category'],
            'news_date' => $row['news_date'],
            'author' => $row['author']
        ]);
    } else {
        // Article not found
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Article not found'
        ]);
    }
    
    $stmt->close();
} else {
    // Invalid article ID
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid article ID'
    ]);
}

$conn->close();
?>
