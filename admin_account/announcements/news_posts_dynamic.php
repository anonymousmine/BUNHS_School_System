<?php
include '../../db_connection.php';

// Fetch latest 4 news posts for the news posts section
$query = "SELECT * FROM news ORDER BY created_at DESC LIMIT 4";
$result = $conn->query($query);
$news_posts = [];
while ($row = $result->fetch_assoc()) {
    $news_posts[] = $row;
}

if (count($news_posts) == 0) {
    echo '<p class="text-muted p-3 text-center">No news posts available.</p>';
} else {
    foreach ($news_posts as $post) {
        $image = !empty($post['image']) ? "../../assets/img/blog/" . $post['image'] : "../../assets/img/blog/default.webp";
        $date = date("D, M d", strtotime($post['news_date']));
        $excerpt = substr($post['short_description'], 0, 100) . '...'; // Truncate to 100 chars
        echo '
        <div class="col-xl-3 col-md-6">
          <div class="post-box">
            <div class="post-img"><img src="' . $image . '" class="img-fluid" alt="' . htmlspecialchars($post['title']) . '"></div>
            <div class="meta">
              <span class="post-date">' . $date . '</span>
              <span class="post-author"> / ' . htmlspecialchars($post['author']) . '</span>
            </div>
            <h3 class="post-title">' . htmlspecialchars($post['title']) . '</h3>
            <p>' . htmlspecialchars($excerpt) . '</p>
            <a href="#" class="readmore stretched-link"><span>Read More</span><i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
        ';
    }
}
?>
