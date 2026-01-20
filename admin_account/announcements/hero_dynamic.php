<?php
include '../../db_connection.php';

$query = "SELECT * FROM news ORDER BY created_at DESC LIMIT 3"; // 1 featured + 2 secondary
$result = $conn->query($query);
$news = [];
while ($row = $result->fetch_assoc()) {
    $news[] = $row;
}

if (count($news) == 0) {
    // placeholder
    echo '<p class="text-muted p-3">No news uploaded yet.</p>';
} else {
    // featured
    $featured = $news[0];
    $image = !empty($featured['image']) ? "../../assets/img/blog/" . $featured['image'] : "../../assets/img/blog/default.webp";
    $date = date("m/d/Y", strtotime($featured['news_date']));
    echo '
    <!-- Featured Article -->
    <article class="featured-post position-relative mb-4">
      <img src="' . $image . '" alt="' . htmlspecialchars($featured['title']) . '" class="img-fluid">
      <div class="post-overlay">
        <div class="post-content">
          <div class="post-meta">
            <span class="category">' . htmlspecialchars($featured['category']) . '</span>
            <span class="date">' . $date . '</span>
          </div>
          <h2 class="post-title">
            <a href="#">' . htmlspecialchars($featured['title']) . '</a>
          </h2>
          <p class="post-excerpt">' . htmlspecialchars($featured['short_description']) . '</p>
          <div class="post-author">
            <span>by</span>
            <a href="#">' . htmlspecialchars($featured['author']) . '</a>
          </div>
        </div>
      </div>
    </article>
    ';

    // secondary
    echo '<div class="row g-4">';
    for ($i = 1; $i < count($news) && $i < 3; $i++) {
        $item = $news[$i];
        $image = !empty($item['image']) ? "../../assets/img/blog/" . $item['image'] : "../../assets/img/blog/default.webp";
        $date = date("m/d/Y", strtotime($item['news_date']));
        echo '
      <div class="col-md-6">
        <article class="secondary-post">
          <div class="post-image">
            <img src="' . $image . '" alt="' . htmlspecialchars($item['title']) . '" class="img-fluid">
          </div>
          <div class="post-content">
            <div class="post-meta">
              <span class="category">' . htmlspecialchars($item['category']) . '</span>
              <span class="date">' . $date . '</span>
            </div>
            <h3 class="post-title">
              <a href="#">' . htmlspecialchars($item['title']) . '</a>
            </h3>
            <div class="post-author">
              <span>by</span>
              <a href="#">' . htmlspecialchars($item['author']) . '</a>
            </div>
          </div>
        </article>
      </div>
        ';
    }
    echo '</div>';
}
?>
