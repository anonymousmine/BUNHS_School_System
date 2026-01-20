<?php

// Top Stories - Latest 5 news
$query_top = "SELECT * FROM news ORDER BY created_at DESC LIMIT 5";
$result_top = $conn->query($query_top);
$top_stories = [];
while ($row = $result_top->fetch_assoc()) {
    $top_stories[] = $row;
}

// Trending News - Next 5 news (OFFSET 5)
$query_trending = "SELECT * FROM news ORDER BY created_at DESC LIMIT 5 OFFSET 5";
$result_trending = $conn->query($query_trending);
$trending_news = [];
while ($row = $result_trending->fetch_assoc()) {
    $trending_news[] = $row;
}

// Latest News - Next 5 after trending (OFFSET 10)
$query_latest = "SELECT * FROM news ORDER BY created_at DESC LIMIT 5 OFFSET 10";
$result_latest = $conn->query($query_latest);
$latest_news = [];
while ($row = $result_latest->fetch_assoc()) {
    $latest_news[] = $row;
}

// Function to render article
function render_tab_article($item) {
    $image = !empty($item['image']) ? "assets/img/blog/" . $item['image'] : "assets/img/blog/default.webp";
    $date = date("m/d/Y", strtotime($item['news_date']));
    return '
    <article class="tab-post">
      <div class="row g-0 align-items-center">
        <div class="col-4">
          <img src="' . $image . '" alt="' . htmlspecialchars($item['title']) . '" class="img-fluid">
        </div>
        <div class="col-8">
          <div class="post-content">
            <span class="category">' . htmlspecialchars($item['category']) . '</span>
            <h4 class="post-title"><a href="#">' . htmlspecialchars($item['title']) . '</a></h4>
            <div class="post-author">by <a href="#">' . htmlspecialchars($item['author']) . '</a></div>
          </div>
        </div>
      </div>
    </article>
    ';
}
?>

<!-- Top Stories Tab -->
<div class="tab-pane fade show active" id="top-stories">
  <?php
  if (count($top_stories) > 0) {
      foreach ($top_stories as $item) {
          echo render_tab_article($item);
      }
  } else {
      echo '<p class="text-muted p-3 text-center">No news uploaded yet.</p>';
  }
  ?>
</div>

<!-- Trending News Tab -->
<div class="tab-pane fade" id="trending">
  <?php
  if (count($trending_news) > 0) {
      foreach ($trending_news as $item) {
          echo render_tab_article($item);
      }
  } else {
      echo '<p class="text-muted p-3 text-center">No news uploaded yet.</p>';
  }
  ?>
</div>

<!-- Latest News Tab -->
<div class="tab-pane fade" id="latest">
  <?php
  if (count($latest_news) > 0) {
      foreach ($latest_news as $item) {
          echo render_tab_article($item);
      }
  } else {
      echo '<p class="text-muted p-3">No news uploaded yet.</p>';
  }
  ?>
</div>
