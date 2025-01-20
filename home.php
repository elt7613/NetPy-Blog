<?php
require_once 'config.php';
require_once 'functions.php';

// Get featured posts
$featured_sql = "SELECT p.*, c.name as category_name, u.username as author_name,
                GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags 
                FROM posts p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN users u ON p.author_id = u.id 
                LEFT JOIN post_tags pt ON p.id = pt.post_id
                LEFT JOIN tags t ON pt.tag_id = t.id AND t.deleted_at IS NULL AND t.is_active = 1
                WHERE p.status = 'published' 
                AND p.featured = 1 
                AND p.deleted_at IS NULL 
                AND p.is_active = 1
                AND c.deleted_at IS NULL 
                AND c.is_active = 1
                AND u.deleted_at IS NULL 
                AND u.is_active = 1
                GROUP BY p.id, c.name, u.username, p.title, p.slug, p.content, p.created_at, p.image_path, p.views
                ORDER BY p.created_at DESC";
$featured_posts = $conn->query($featured_sql)->fetch_all(MYSQLI_ASSOC);

// Get categories with post count
$categories_sql = "SELECT c.*, COUNT(p.id) as post_count 
                  FROM categories c 
                  LEFT JOIN posts p ON c.id = p.category_id 
                  AND p.status = 'published' 
                  AND p.deleted_at IS NULL 
                  AND p.is_active = 1
                  WHERE c.deleted_at IS NULL 
                  AND c.is_active = 1
                  GROUP BY c.id 
                  HAVING post_count > 0
                  ORDER BY c.name";
$categories_result = $conn->query($categories_sql);
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

// Get all published posts
$posts_sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, u.username as author_name,
              GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags,
              GROUP_CONCAT(DISTINCT t.slug ORDER BY t.name ASC SEPARATOR ', ') as tag_slugs
              FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN users u ON p.author_id = u.id 
              LEFT JOIN post_tags pt ON p.id = pt.post_id
              LEFT JOIN tags t ON pt.tag_id = t.id AND t.deleted_at IS NULL AND t.is_active = 1
              WHERE p.status = 'published'
              AND p.deleted_at IS NULL 
              AND p.is_active = 1
              AND c.deleted_at IS NULL 
              AND c.is_active = 1
              AND u.deleted_at IS NULL 
              AND u.is_active = 1
              GROUP BY p.id, c.name, c.slug, u.username, p.title, p.slug, p.content, p.created_at, p.image_path, p.views
              ORDER BY p.created_at DESC";
$posts_result = $conn->query($posts_sql);
$posts = $posts_result ? $posts_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetPy | Blog</title>

    <!-- Bootstrap core CSS -->
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Additional CSS Files -->
    <link rel="stylesheet" href="assets/css/fontawesome.css">
    <link rel="stylesheet" href="assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="assets/css/owl.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

    <link rel="icon" type="image/png" href="images/fav-icon.jpg">
    <link rel="stylesheet" href="blog_page_css/section_1.css">
    <link rel="stylesheet" href="blog_page_css/section_2.css">

    <link href='http://fonts.googleapis.com/css?family=Roboto' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Urbanist' rel='stylesheet'>
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

    <!--==================== SECTION 1 ===========================-->
    <section class="section-1">
        <div class="hero">
            <div class="slider-container">
                <?php foreach ($featured_posts as $post): ?>
                <div class="slide">
                    <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>">
                        <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        <div class="hero-content">
                            <div class="meta-category">
                                <span><?php echo htmlspecialchars($post['category_name']); ?></span>
                            </div>
                            <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                            <ul class="post-info">
                                <li><?php echo htmlspecialchars($post['author_name']); ?></li>
                                <li><?php echo date('M d, Y', strtotime($post['created_at'])); ?></li>
                                <li><?php echo $post['views']; ?> Views</li>
                            </ul>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="slider-arrows">
                <button class="arrow prev">❮</button>
                <button class="arrow next">❯</button>
            </div>
        </div>

        <div class="slider-dots">
            <?php foreach ($featured_posts as $index => $post): ?>
            <div class="dot <?php echo $index === 0 ? 'active' : ''; ?>"></div>
            <?php endforeach; ?>
        </div>
    </section>

    <!--==================== SECTION 2 ===========================-->
    <section class="section-2">
        <div class="search-container">
            <input type="text" class="search-input" placeholder="Search blogs...">
            <button class="search-button">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24">
                    <path fill="white" d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5A6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5S14 7.01 14 9.5S11.99 14 9.5 14" />
                </svg>
            </button>
        </div>

        <div class="tags-container">
            <span class="tag active" data-category="all">All Posts</span>
            <?php 
            $category_count = 0;
            foreach ($categories as $category): 
                if ($category_count < 10):
            ?>
                <span class="tag" data-category="<?php echo htmlspecialchars($category['slug']); ?>">
                    <?php echo htmlspecialchars($category['name']); ?>
                </span>
            <?php 
                endif;
                $category_count++;
            endforeach; 
            
            if (count($categories) > 10):
            ?>
                <span class="tag show-more-btn">Show More...</span>
            <?php endif; ?>
        </div>

        <!-- Categories Popup -->
        <div class="categories-popup" id="categoriesPopup">
            <div class="popup-content">
                <div class="popup-header">
                    <h2>All Categories</h2>
                    <span class="close-popup">&times;</span>
                </div>
                <div class="popup-categories">
                    <span class="popup-tag active" data-category="all">All Posts</span>
                    <?php foreach ($categories as $category): ?>
                    <span class="popup-tag" data-category="<?php echo htmlspecialchars($category['slug']); ?>">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <style>
            .categories-popup {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1000;
                justify-content: center;
                align-items: center;
            }

            .popup-content {
                background-color: white;
                padding: 30px;
                border-radius: 15px;
                width: 90%;
                max-width: 800px;
                max-height: 80vh;
                overflow-y: auto;
                position: relative;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            }

            .popup-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 1px solid #eee;
            }

            .popup-header h2 {
                margin: 0;
                font-size: 1.5rem;
                color: #333;
            }

            .close-popup {
                font-size: 28px;
                font-weight: bold;
                color: #666;
                cursor: pointer;
                transition: color 0.3s;
            }

            .close-popup:hover {
                color: #333;
            }

            .popup-categories {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .popup-tag {
                padding: 8px 16px;
                border: 1.5px solid #ccc;
                border-radius: 20px;
                font-size: 1rem;
                font-weight: 400;
                color: #0047CC;
                cursor: pointer;
                transition: all 0.3s;
                white-space: nowrap;
            }

            .popup-tag:hover, .popup-tag.active {
                background: #0047CC;
                color: white;
                border-color: #0047CC;
            }

            .show-more-btn {
                background-color: #f8f9fa;
                border: 1.5px solid #0047CC;
                color: #0047CC;
            }

            .show-more-btn:hover {
                background-color: #0047CC;
                color: white;
            }

            /* Custom scrollbar for popup */
            .popup-content::-webkit-scrollbar {
                width: 6px;
            }

            .popup-content::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }

            .popup-content::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 10px;
            }

            .popup-content::-webkit-scrollbar-thumb:hover {
                background: #555;
            }
        </style>

        <div class="blog-header">
            <h1>Blogs - </h1>
            <h1>
                <span class="filter-type">All Posts</span>
            </h1>
        </div>

        <div class="blog-grid" id="blogGrid">
            <?php foreach ($posts as $post): ?>
            <div class="blog-card visible" 
                data-category="<?php echo htmlspecialchars($post['category_slug']); ?>"
                data-category-name="<?php echo htmlspecialchars($post['category_name']); ?>"
                data-tags="<?php echo htmlspecialchars($post['post_tags']); ?>"
                data-tag-slugs="<?php echo htmlspecialchars($post['tag_slugs']); ?>">
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
                            foreach ($tags as $tag): 
                            ?>
                            <span class="blog-tag"><?php echo htmlspecialchars($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <span class="learn-more">Read More >></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="blog-actions">
            <button class="load-more" id="loadMore">Load More</button>
            <button class="show-less" id="showLess" style="display: none;">Show Less...</button>
        </div>

        <style>
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
            }
        </style>
    </section>

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

    <script src="blog_page_js/section_1.js"></script>
    <script src="blog_page_js/section_2.js"></script>

    <script>
        $(document).ready(function() {
            var owl = $('.owl-carousel');
            owl.owlCarousel({
                items: 1,
                loop: true,
                dots: true,
                nav: true,
                autoplay: true,
                autoplayTimeout: 5000,
                autoplayHoverPause: true
            });
        });

        $('.custom-carousel').owlCarousel({
            autoplay: true,
            autoplayTimeout: 5000,
            autoplayHoverPause: true,
            items: 1,
            nav: true,
            dots: true,
            loop: true
        });
    </script>
</body>

</html>