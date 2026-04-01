<?php
// Dynamic news posts
// This file is included by news.php for displaying news posts
?>
<!-- News Posts Grid -->
<div class="news-posts-grid">
    <?php
    // Sample news posts - in production, these would come from database
    $sample_posts = [
        [
            'title' => 'BUNHS Celebrates 25th Founding Anniversary',
            'excerpt' => 'Buyoan National High School marks a quarter-century of educational excellence with a week-long celebration...',
            'date' => '2024-06-01',
            'category' => 'Events',
            'image' => 'assets/img/education/anniversary.jpg'
        ],
        [
            'title' => 'Students Excel in Regional Science Fair',
            'excerpt' => 'Three BUNHS students brought home medals from the Regional Science and Technology Fair...',
            'date' => '2024-05-28',
            'category' => 'Academics',
            'image' => 'assets/img/education/science-fair.jpg'
        ],
        [
            'title' => 'New Computer Lab Inauguration',
            'excerpt' => 'The school inaugurated a state-of-the-art computer laboratory to enhance digital literacy...',
            'date' => '2024-05-20',
            'category' => 'Facilities',
            'image' => 'assets/img/education/computer-lab.jpg'
        ],
        [
            'title' => 'Teachers Attend Professional Development Workshop',
            'excerpt' => 'Faculty members participated in a comprehensive professional development program...',
            'date' => '2024-05-15',
            'category' => 'Faculty',
            'image' => 'assets/img/education/workshop.jpg'
        ],
        [
            'title' => 'Sports Meet 2024: A Display of Excellence',
            'excerpt' => 'The annual sports meet showcased outstanding athletic performances and sportsmanship...',
            'date' => '2024-05-10',
            'category' => 'Sports',
            'image' => 'assets/img/education/sports-meet.jpg'
        ],
        [
            'title' => 'Community Outreach Program Success',
            'excerpt' => 'BUNHS students and teachers successfully conducted a community outreach program...',
            'date' => '2024-05-05',
            'category' => 'Community',
            'image' => 'assets/img/education/outreach.jpg'
        ]
    ];
    
    foreach ($sample_posts as $post):
    ?>
    <article class="news-post">
        <div class="post-image">
            <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="img-fluid">
            <div class="post-category"><?php echo htmlspecialchars($post['category']); ?></div>
        </div>
        <div class="post-content">
            <div class="post-meta">
                <span class="post-date"><?php echo date('F j, Y', strtotime($post['date'])); ?></span>
            </div>
            <h3 class="post-title">
                <a href="#"><?php echo htmlspecialchars($post['title']); ?></a>
            </h3>
            <p class="post-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
            <a href="#" class="read-more">Read More →</a>
        </div>
    </article>
    <?php endforeach; ?>
</div>
