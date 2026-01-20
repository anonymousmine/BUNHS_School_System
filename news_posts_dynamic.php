<?php

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
        $image = !empty($post['image']) ? "assets/img/blog/" . $post['image'] : "assets/img/blog/default.webp";
        $date = date("D, M d", strtotime($post['news_date']));
        $excerpt = substr($post['short_description'], 0, 100) . '...'; // Truncate to 100 chars
        echo '
        <div class="col-xl-3 col-md-6 news-post">
          <div class="post-box">
            <div class="post-img">
              <img src="' . $image . '" class="img-fluid" alt="' . htmlspecialchars($post['title']) . '">
              <button class="close-btn" onclick="toggleComment(' . $post['id'] . ')" style="display:none;">&times;</button>
            </div>
            <div class="post-content-expanded" style="display:none;">
              <div class="full-description">' . htmlspecialchars($post['short_description']) . '</div>
            </div>
            <div class="interaction-bar" data-post-id="' . $post['id'] . '">
              <button class="interaction-btn like-btn" onclick="toggleLike(' . $post['id'] . ')">
                <i class="far fa-heart"></i> <span class="like-count" id="like-count-' . $post['id'] . '">0</span>
              </button>
              <button class="interaction-btn comment-btn" onclick="toggleComment(' . $post['id'] . ')">
                <i class="far fa-comment"></i> <span class="comment-count" id="comment-count-' . $post['id'] . '">0</span>
              </button>
              <button class="interaction-btn share-btn" onclick="sharePost(' . $post['id'] . ', \'' . addslashes($post['title']) . '\')">
                <i class="fas fa-share"></i>
              </button>
            </div>
            <div class="comment-section" id="comment-section-' . $post['id'] . '" style="display:none;">
              <input type="text" placeholder="Add a comment..." class="comment-input" onkeypress="addComment(event, ' . $post['id'] . ')">
              <div class="comments-list" id="comments-list-' . $post['id'] . '">
                <!-- Comments will be added here -->
              </div>
            </div>
            <div class="meta">
              <span class="post-date">' . $date . '</span>
              <span class="post-author"> / ' . htmlspecialchars($post['author']) . '</span>
            </div>
            <h3 class="post-title">' . htmlspecialchars($post['title']) . '</h3>
            <p class="excerpt">' . htmlspecialchars($excerpt) . '</p>
            <a href="#" class="readmore stretched-link"><span>Read More</span><i class="bi bi-arrow-right"></i></a>
          </div>
        </div>
        ';
    }
}
