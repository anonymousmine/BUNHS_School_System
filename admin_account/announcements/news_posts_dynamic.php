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
        $image = !empty($post['image']) ? "/assets/img/blog/" . $post['image'] : "/assets/img/blog/default.webp";
        $date = date("D, M d", strtotime($post['news_date']));
        $excerpt = substr($post['short_description'], 0, 100) . '...'; // Truncate to 100 chars
        $fullContent = $post['content']; // Full content for expanded view
        echo '
        <div class="col-xl-3 col-md-6 news-post" data-post-id="' . $post['id'] . '">
          <div class="post-box">
            <div class="post-img"><img src="' . $image . '" class="img-fluid" alt="' . htmlspecialchars($post['title']) . '"></div>
            <div class="meta">
              <span class="post-date">' . $date . '</span>
              <span class="post-author"> / ' . htmlspecialchars($post['author']) . '</span>
            </div>
            <h3 class="post-title">' . htmlspecialchars($post['title']) . '</h3>
            <p class="excerpt">' . htmlspecialchars($excerpt) . '</p>
            <p class="full-description" style="display: none;">' . htmlspecialchars($fullContent) . '</p>
            <a href="#" class="readmore stretched-link" onclick="togglePost(' . $post['id'] . '); return false;"><span>Read More</span><i class="bi bi-arrow-right"></i></a>
            <div class="interaction-bar" data-post-id="' . $post['id'] . '">
              <button class="interaction-btn like-btn" onclick="toggleLike(' . $post['id'] . '); event.stopPropagation();">
                <i class="far fa-heart"></i> <span class="like-count" id="like-count-' . $post['id'] . '">0</span>
              </button>
              <button class="interaction-btn comment-btn" onclick="toggleComment(' . $post['id'] . '); event.stopPropagation();">
                <i class="far fa-comment"></i> <span class="comment-count" id="comment-count-' . $post['id'] . '">0</span>
              </button>
              <button class="interaction-btn share-btn" onclick="sharePost(' . $post['id'] . ', \'' . addslashes($post['title']) . '\'); event.stopPropagation();">
                <i class="fas fa-share"></i>
              </button>
              <button class="interaction-btn delete-btn delete-post" data-id="' . $post['id'] . '" data-title="' . addslashes($post['title']) . '" title="Delete Post" onclick="event.stopPropagation();">
                <i class="fas fa-trash"></i>
              </button>
            </div>
            <div class="comment-section" id="comment-section-' . $post['id'] . '" style="display: none;">
              <input type="text" placeholder="Add a comment..." class="comment-input" onkeypress="addComment(event, ' . $post['id'] . ')">
              <div class="comments-list" id="comments-list-' . $post['id'] . '">
                <!-- Comments will be added here -->
              </div>
            </div>
          </div>
        </div>
        ';
    }
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
        padding: 20px;
    }

    .news-post.expanded .post-img {
        position: relative;
        width: 100%;
        height: 300px;
        overflow: hidden;
        border-radius: 8px 8px 0 0;
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

    .news-post .readmore {
        display: inline-block;
        margin-top: 10px;
    }
</style>

<script>
    function togglePost(postId) {
        const newsPost = document.querySelector(`.news-post[data-post-id="${postId}"]`);
        const excerpt = newsPost.querySelector('.excerpt');
        const fullDescription = newsPost.querySelector('.full-description');
        const readmore = newsPost.querySelector('.readmore');
        const commentSection = newsPost.querySelector('.comment-section');

        if (newsPost.classList.contains('expanded')) {
            // Collapse
            newsPost.classList.remove('expanded');
            excerpt.style.display = 'block';
            fullDescription.style.display = 'none';
            readmore.innerHTML = '<span>Read More</span><i class="bi bi-arrow-right"></i>';
            commentSection.style.display = 'none';
            // Remove overlay
            const overlay = document.querySelector('.expanded-overlay');
            if (overlay) overlay.remove();
            // Remove close button
            const closeBtn = newsPost.querySelector('.close-btn');
            if (closeBtn) closeBtn.remove();
        } else {
            // Expand
            newsPost.classList.add('expanded');
            excerpt.style.display = 'none';
            fullDescription.style.display = 'block';
            readmore.innerHTML = '<span>Show Less</span><i class="bi bi-arrow-up"></i>';
            commentSection.style.display = 'block';

            // Add overlay
            const overlay = document.createElement('div');
            overlay.className = 'expanded-overlay';
            overlay.onclick = () => togglePost(postId);
            document.body.appendChild(overlay);

            // Add close button
            const postBox = newsPost.querySelector('.post-box');
            const closeBtn = document.createElement('button');
            closeBtn.className = 'close-btn';
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = (e) => {
                e.stopPropagation();
                togglePost(postId);
            };
            postBox.insertBefore(closeBtn, postBox.firstChild);
        }
    }

    function toggleLike(postId) {
        const likeBtn = document.querySelector(`.news-post[data-post-id="${postId}"] .like-btn`);
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
        const newsPost = document.querySelector(`.news-post[data-post-id="${postId}"]`);
        const commentSection = newsPost.querySelector('.comment-section');

        if (newsPost.classList.contains('expanded')) {
            // Already expanded, just toggle comment section
            commentSection.style.display = commentSection.style.display === 'none' ? 'block' : 'none';
        } else {
            // Expand first then show comments
            togglePost(postId);
            commentSection.style.display = 'block';
        }
    }

    function addComment(event, postId) {
        if (event.key === 'Enter' && event.target.value.trim()) {
            const commentText = event.target.value.trim();
            const commentsList = document.getElementById(`comments-list-${postId}`);
            const commentCount = document.querySelector(`.news-post[data-post-id="${postId}"] .comment-count`);

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
        document.querySelectorAll('.news-post').forEach(post => {
            const postId = post.dataset.postId;
            if (localStorage.getItem(`liked-${postId}`)) {
                const likeBtn = post.querySelector('.like-btn');
                likeBtn.classList.add('liked');
                const likeCount = post.querySelector('.like-count');
                likeCount.textContent = parseInt(likeCount.textContent) + 1;
            }
        });
    });
</script>