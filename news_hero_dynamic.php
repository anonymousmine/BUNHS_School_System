<?php

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
    $image = !empty($featured['image']) ? "assets/img/blog/" . $featured['image'] : "assets/img/blog/default.webp";
    $date = date("m/d/Y", strtotime($featured['news_date']));
    echo '
    <!-- Featured Article -->
    <article class="featured-post position-relative mb-4">
      <div class="image-container position-relative">
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
      </div>
      <div class="interaction-bar" data-post-id="' . $featured['id'] . '">
        <button class="interaction-btn like-btn" onclick="toggleLike(' . $featured['id'] . ')">
          <i class="far fa-heart"></i> <span class="like-count" id="like-count-' . $featured['id'] . '">0</span>
        </button>
        <button class="interaction-btn comment-btn" onclick="toggleComment(' . $featured['id'] . ')">
          <i class="far fa-comment"></i> <span class="comment-count" id="comment-count-' . $featured['id'] . '">0</span>
        </button>
        <button class="interaction-btn share-btn" onclick="sharePost(' . $featured['id'] . ', \'' . addslashes($featured['title']) . '\')">
          <i class="fas fa-share"></i>
        </button>
      </div>
      <div class="comment-section" id="comment-section-' . $featured['id'] . '" style="display:none;">
        <input type="text" placeholder="Add a comment..." class="comment-input" onkeypress="addComment(event, ' . $featured['id'] . ')">
        <div class="comments-list" id="comments-list-' . $featured['id'] . '">
          <!-- Comments will be added here -->
        </div>
      </div>
    </article>
    ';

    // secondary
    echo '<div class="row g-4">';
    for ($i = 1; $i < count($news) && $i < 3; $i++) {
        $item = $news[$i];
        $image = !empty($item['image']) ? "assets/img/blog/" . $item['image'] : "assets/img/blog/default.webp";
        $date = date("m/d/Y", strtotime($item['news_date']));
        echo '
      <div class="col-md-6">
        <article class="secondary-post">
          <div class="post-image">
            <img src="' . $image . '" alt="' . htmlspecialchars($item['title']) . '" class="img-fluid">
          </div>
          <div class="interaction-bar" data-post-id="' . $item['id'] . '">
            <button class="interaction-btn like-btn" onclick="toggleLike(' . $item['id'] . ')">
              <i class="far fa-heart"></i> <span class="like-count" id="like-count-' . $item['id'] . '">0</span>
            </button>
            <button class="interaction-btn comment-btn" onclick="toggleComment(' . $item['id'] . ')">
              <i class="far fa-comment"></i> <span class="comment-count" id="comment-count-' . $item['id'] . '">0</span>
            </button>
            <button class="interaction-btn share-btn" onclick="sharePost(' . $item['id'] . ', \'' . addslashes($item['title']) . '\')">
              <i class="fas fa-share"></i>
            </button>
          </div>
          <div class="comment-section" id="comment-section-' . $item['id'] . '" style="display:none;">
            <input type="text" placeholder="Add a comment..." class="comment-input" onkeypress="addComment(event, ' . $item['id'] . ')">
            <div class="comments-list" id="comments-list-' . $item['id'] . '">
              <!-- Comments will be added here -->
            </div>
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

<style>
    .interaction-bar {
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 10px 0;
        padding: 5px 0;
        border-top: 1px solid #e9ecef;
        border-bottom: 1px solid #e9ecef;
    }

    .interaction-btn {
        background: none;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 14px;
        color: #6c757d;
        transition: color 0.3s ease;
    }

    .interaction-btn:hover {
        color: #495057;
    }

    .like-btn.liked i {
        color: #dc3545;
    }

    .comment-section {
        margin-top: 10px;
    }

    .comment-input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        margin-bottom: 10px;
    }

    .comments-list {
        max-height: 150px;
        overflow-y: auto;
    }

    .comment-item {
        padding: 5px 0;
        border-bottom: 1px solid #f8f9fa;
        font-size: 14px;
    }

    .news-post.expanded {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        z-index: 9999;
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .news-post.expanded .post-box {
        height: auto;
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow-y: auto;
    }

    .news-post.expanded .post-img {
        position: relative;
        width: 100%;
        height: 400px;
        overflow: hidden;
    }

    .news-post.expanded .post-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .news-post.expanded .close-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(0, 0, 0, 0.5);
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        cursor: pointer;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }

    .news-post.expanded .post-content-expanded {
        padding: 20px;
        flex: 1;
        overflow-y: auto;
    }

    .news-post.expanded .interaction-bar {
        padding: 15px 20px;
        border-top: 1px solid #e9ecef;
        background: #f8f9fa;
    }

    .expanded-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9998;
    }
</style>

<script>
    function toggleLike(postId) {
        const likeBtn = document.querySelector(`.interaction-bar[data-post-id="${postId}"] .like-btn`);
        const likeCount = document.getElementById(`like-count-${postId}`);
        const liked = likeBtn.classList.contains('liked');
        const likes = parseInt(likeCount.textContent);

        if (liked) {
            likeBtn.classList.remove('liked');
            likeCount.textContent = likes - 1;
            localStorage.removeItem(`liked-${postId}`);
        } else {
            likeBtn.classList.add('liked');
            likeCount.textContent = likes + 1;
            localStorage.setItem(`liked-${postId}`, 'true');
        }
    }

    function toggleComment(postId) {
        const newsPost = document.querySelector(`.news-post:has([data-post-id="${postId}"])`);
        const commentSection = document.getElementById(`comment-section-${postId}`);

        if (newsPost) {
            // For news posts section
            const excerpt = newsPost.querySelector('.excerpt');
            const fullDescription = newsPost.querySelector('.full-description');

            if (newsPost.classList.contains('expanded')) {
                // Collapse
                newsPost.classList.remove('expanded');
                excerpt.style.display = 'block';
                fullDescription.style.display = 'none';
                commentSection.style.display = 'none';
                // Remove overlay
                const overlay = document.querySelector('.expanded-overlay');
                if (overlay) overlay.remove();
            } else {
                // Expand
                newsPost.classList.add('expanded');
                excerpt.style.display = 'none';
                fullDescription.style.display = 'block';
                commentSection.style.display = 'block';
                // Add overlay
                const overlay = document.createElement('div');
                overlay.className = 'expanded-overlay';
                overlay.onclick = () => toggleComment(postId); // Click overlay to close
                document.body.appendChild(overlay);
            }
        } else {
            // For hero section
            commentSection.style.display = commentSection.style.display === 'none' ? 'block' : 'none';
        }
    }

    function addComment(event, postId) {
        if (event.key === 'Enter' && event.target.value.trim()) {
            const commentText = event.target.value.trim();
            const commentsList = document.getElementById(`comments-list-${postId}`);
            const commentCount = document.getElementById(`comment-count-${postId}`);

            const commentItem = document.createElement('div');
            commentItem.className = 'comment-item';
            commentItem.textContent = commentText;
            commentsList.appendChild(commentItem);

            commentCount.textContent = parseInt(commentCount.textContent) + 1;
            event.target.value = '';
        }
    }

    function sharePost(postId, title) {
        const url = window.location.href;
        if (navigator.share) {
            navigator.share({
                title: title,
                url: url
            });
        } else {
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copied to clipboard!');
            });
        }
    }

    // Initialize likes from localStorage
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.interaction-bar').forEach(bar => {
            const postId = bar.dataset.postId;
            if (localStorage.getItem(`liked-${postId}`)) {
                const likeBtn = bar.querySelector('.like-btn');
                likeBtn.classList.add('liked');
                const likeCount = bar.querySelector('.like-count');
                likeCount.textContent = parseInt(likeCount.textContent) + 1;
            }
        });
    });
</script>