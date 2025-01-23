<?php
require_once 'config.php';
require_once 'functions.php';

// Get tag slug from URL
$tag_slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';
if (empty($tag_slug)) {
    header('Location: home.php');
    exit;
}

// Get tag info
$sql = "SELECT * FROM tags 
        WHERE slug = ? 
        AND deleted_at IS NULL 
        AND is_active = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tag_slug);
$stmt->execute();
$tag = $stmt->get_result()->fetch_assoc();

if (!$tag) {
    header('Location: home.php');
    exit();
}

// Get current page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$posts_per_page = 10;
$offset = ($page - 1) * $posts_per_page;

// Get total number of posts with this tag
$count_sql = "SELECT COUNT(DISTINCT p.id) as total 
              FROM posts p
              LEFT JOIN post_tags pt ON p.id = pt.post_id
              LEFT JOIN tags t ON pt.tag_id = t.id
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN users u ON p.author_id = u.id 
              WHERE t.id = ? 
              AND p.status = 'published'
              AND p.deleted_at IS NULL 
              AND p.is_active = 1
              AND t.deleted_at IS NULL 
              AND t.is_active = 1
              AND c.deleted_at IS NULL 
              AND c.is_active = 1
              AND u.deleted_at IS NULL 
              AND u.is_active = 1";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param("i", $tag['id']);
$stmt->execute();
$total_posts = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Ensure current page is within valid range
$page = max(1, min($page, $total_pages));

// Get posts with pagination
$sql = "SELECT p.*, c.name as category_name, u.username as author_name,
        GROUP_CONCAT(DISTINCT t2.name ORDER BY t2.name ASC SEPARATOR ', ') as post_tags 
        FROM posts p 
        LEFT JOIN post_tags pt ON p.id = pt.post_id
        LEFT JOIN tags t ON pt.tag_id = t.id
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.author_id = u.id 
        LEFT JOIN post_tags pt2 ON p.id = pt2.post_id
        LEFT JOIN tags t2 ON pt2.tag_id = t2.id
        WHERE t.id = ? 
        AND p.status = 'published'
        AND p.deleted_at IS NULL 
        AND p.is_active = 1
        AND t.deleted_at IS NULL 
        AND t.is_active = 1
        AND c.deleted_at IS NULL 
        AND c.is_active = 1
        AND u.deleted_at IS NULL 
        AND u.is_active = 1
        AND (t2.id IS NULL OR (t2.deleted_at IS NULL AND t2.is_active = 1))
        GROUP BY p.id 
        ORDER BY p.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $tag['id'], $posts_per_page, $offset);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for sidebar
$categories = getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Posts tagged with <?php echo htmlspecialchars($tag['name']); ?>">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link href='https://fonts.googleapis.com/css?family=Urbanist' rel='stylesheet'>

    <title>Posts tagged with <?php echo htmlspecialchars($tag['name']); ?> - NetPy Blog</title>

    <!-- Bootstrap core CSS -->
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Additional CSS Files -->
    <link rel="stylesheet" href="assets/css/fontawesome.css">
    <link rel="stylesheet" href="assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="assets/css/owl.css">
    <link rel="stylesheet" href="blog_page_css/section_1.css">
    <link rel="stylesheet" href="blog_page_css/section_2.css">
    
    <style>
        /* Preloader styles */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #fff;
            z-index: 99999;
        }

        #preloader .jumper {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            gap: 8px;
        }

        #preloader .jumper > div {
            width: 10px;
            height: 10px;
            background-color: #0047CC;
            border-radius: 50%;
            animation: jumper 0.6s infinite alternate;
        }

        #preloader .jumper div:nth-child(2) {
            animation-delay: 0.2s;
        }

        #preloader .jumper div:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes jumper {
            from {
                transform: translateY(0);
            }
            to {
                transform: translateY(-10px);
            }
        }

        .search-container {
            max-width: 800px;
            margin: 30px auto;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 20px 60px 15px 30px;
            border: 1px solid #ccc;
            border-radius: 30px;
            background: #f8f9fa;
            font-size: 1rem;
            color: #181818;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .search-button {
            position: absolute;
            right: 5px;
            top: 33%;
            transform: translateY(-50%);
            background: #0047CC;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-button:hover {
            background: #003399;
        }

        .blog-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(300px, 1fr));
            gap: 30px;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
            justify-content: center;
        }

        @media (max-width: 1200px) {
            .blog-grid {
                grid-template-columns: repeat(2, minmax(280px, 1fr));
                max-width: 900px;
            }
        }

        @media (max-width: 768px) {
            .blog-grid {
                grid-template-columns: minmax(280px, 1fr);
                max-width: 500px;
                padding: 15px;
            }
        }

        .blog-card {
            display: none;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            max-width: 380px;
            margin: 0 auto;
            width: 100%;
        }

        .blog-card.visible {
            display: block;
        }

        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .blog-card a {
            text-decoration: none;
            color: inherit;
        }

        .blog-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .blog-content {
            padding: 20px;
        }

        .blog-title {
            font-size: 1.2rem;
            margin: 0 0 10px;
            color: #333;
            font-weight: 600;
        }

        .blog-date {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 15px;
        }

        .blog-date span {
            color: #0047CC;
            font-weight: 500;
        }

        .blog-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .blog-tag {
            background: #f0f2f5;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            color: #666;
        }

        .learn-more {
            display: inline-block;
            margin-top: 15px;
            color: #0047CC;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .tag-header {
            text-align: center;
            padding: 150px 0px;
            background: linear-gradient(to right, #f8f9fa, #f0f2f5);
            border-bottom: 1px solid #eee;
        }

        .tag-label {
            display: inline-block;
            padding: 5px 15px;
            background-color: #0047CC;
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .tag-header h1 {
            font-size: 3rem;
            color: #333;
            margin: 10px 0;
            font-weight: 700;
            font-family: 'Urbanist', sans-serif;
        }

        .tag-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            color: #666;
            font-size: 1.1rem;
            margin-top: 15px;
        }

        .post-count {
            font-weight: 500;
            color: #0047CC;
        }

        .separator {
            color: #ccc;
        }

        .blog-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
            margin: 30px 0;
        }

        .load-more, .show-less {
            display: block;
            width: 200px;
            padding: 12px 24px;
            background: none;
            color: #545657;
            font-size: 1.2rem;
            font-weight: 600;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .load-more {
            border: 1.5px solid #0047CC;
        }

        .show-less {
            border: none;
            color: #0047CC;
        }

        .load-more:hover {
            background: #0047CC;
            color: white;
        }

        .show-less:hover {
            color: #0a3679;
            text-decoration: underline;
        }

        @media screen and (max-width: 768px) {
            .blog-actions {
                gap: 10px;
            }

            .load-more, .show-less {
                width: 150px;
                font-size: 1rem;
            }

            .tag-header {
                padding: 130px 0px;
            }

            .tag-header h1 {
                font-size: 2.2rem;
            }

            .tag-meta {
                flex-direction: column;
                gap: 8px;
            }

            .separator {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- ***** Preloader Start ***** -->
    <div id="preloader">
        <div class="jumper">
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>  
    <!-- ***** Preloader End ***** -->

    <!-- Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Tag Header -->
    <div class="tag-header">
        <span class="tag-label">Tag</span>
        <h1><?php echo htmlspecialchars($tag['name']); ?></h1>
        <div class="tag-meta">
            <span class="post-count"><?php echo $total_posts; ?> Posts</span>
            <span class="separator">•</span>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-container">
        <input type="text" class="search-input" placeholder="Search in <?php echo htmlspecialchars($tag['name']); ?>...">
        <button class="search-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24">
                <path fill="white" d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5A6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5S14 7.01 14 9.5S11.99 14 9.5 14" />
            </svg>
        </button>
    </div>

    <!-- Blog Grid -->
    <div class="blog-grid" id="blogGrid">
        <?php if (empty($posts)): ?>
            <div class="no-posts">
                <p>No posts found with this tag.</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
            <div class="blog-card">
                <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>">
                    <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="blog-image">
                    <div class="blog-content">
                        <h3 class="blog-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="blog-date">
                            <span><?php echo htmlspecialchars($post['category_name']); ?></span> | 
                            <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                        </p>
                        <?php if (!empty($post['post_tags'])): ?>
                        <div class="blog-tags">
                            <?php 
                            $tags = explode(', ', $post['post_tags']);
                            foreach ($tags as $tag_name): 
                            ?>
                            <span class="blog-tag"><?php echo htmlspecialchars($tag_name); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <span class="learn-more">Read More >></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Blog Actions -->
    <div class="blog-actions">
        <button class="load-more" id="loadMore">Load More</button>
        <button class="show-less" id="showLess" style="display: none;">Show Less...</button>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap core JavaScript -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Additional Scripts -->
    <script src="assets/js/custom.js"></script>
    <script src="assets/js/owl.js"></script>
    <script src="assets/js/slick.js"></script>
    <script src="assets/js/isotope.js"></script>
    <script src="assets/js/accordions.js"></script>

    <script>
        // Preloader handling
        $(window).on('load', function() {
            console.log('Window loaded');
            $('#preloader').fadeOut('slow', function() {
                $(this).remove();
            });

            // Initialize blog cards visibility
            const postsPerPage = 3;
            const blogCards = document.querySelectorAll('.blog-card');
            const loadMoreBtn = document.getElementById('loadMore');
            const showLessBtn = document.getElementById('showLess');
            let currentlyShown = 0;

            function showNextPosts() {
                const cards = Array.from(blogCards);
                const start = currentlyShown;
                const end = Math.min(currentlyShown + postsPerPage, cards.length);

                for (let i = start; i < end; i++) {
                    cards[i].classList.add('visible');
                }

                currentlyShown = end;

                // Update button visibility
                loadMoreBtn.style.display = currentlyShown < cards.length ? 'block' : 'none';
                showLessBtn.style.display = currentlyShown > postsPerPage ? 'block' : 'none';

                // Debug
                console.log('Showing posts from', start, 'to', end, 'Total:', cards.length);
            }

            function showLessPosts() {
                const cards = Array.from(blogCards);
                // Remove one row (3 posts) at a time
                const newEnd = Math.max(postsPerPage, currentlyShown - postsPerPage);
                
                // Hide posts from current position to new position
                for (let i = currentlyShown - 1; i >= newEnd; i--) {
                    cards[i].classList.remove('visible');
                }

                currentlyShown = newEnd;

                // Update button visibility
                loadMoreBtn.style.display = 'block';
                showLessBtn.style.display = currentlyShown > postsPerPage ? 'block' : 'none';

                // Debug
                console.log('Reduced to showing', newEnd, 'posts');
            }

            // Event listeners
            loadMoreBtn.addEventListener('click', showNextPosts);
            showLessBtn.addEventListener('click', showLessPosts);

            // Show initial posts
            showNextPosts();
        });

        // Search functionality
        document.querySelector('.search-input').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const blogCards = document.querySelectorAll('.blog-card');
            
            blogCards.forEach(card => {
                const title = card.querySelector('.blog-title').textContent.toLowerCase();
                const tags = card.querySelector('.blog-tags')?.textContent.toLowerCase() || '';
                const content = title + ' ' + tags;
                
                if (content.includes(searchTerm)) {
                    card.classList.add('visible');
                } else {
                    card.classList.remove('visible');
                }
            });
        });

        // Backup preloader removal
        setTimeout(function() {
            if ($('#preloader').length > 0) {
                console.log('Forcing preloader removal after timeout');
                $('#preloader').remove();
            }
        }, 3000);
    </script>
</body>
</html> 