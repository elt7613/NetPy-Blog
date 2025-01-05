<?php
require_once 'config.php';
require_once 'functions.php';

// Get current page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$posts_per_page = 10;
$offset = ($page - 1) * $posts_per_page;

// Get total number of published posts
$count_sql = "SELECT COUNT(*) as total FROM posts WHERE status = 'published'";
$total_posts = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Ensure current page is within valid range
$page = max(1, min($page, $total_pages));

// Get published posts with pagination
$sql = "SELECT p.*, c.name as category_name, u.username as author_name,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.author_id = u.id 
        LEFT JOIN post_tags pt ON p.id = pt.post_id
        LEFT JOIN tags t ON pt.tag_id = t.id
        WHERE p.status = 'published'
        GROUP BY p.id 
        ORDER BY p.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $posts_per_page, $offset);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get featured posts for the banner
$featured_posts = getFeaturedPosts(6);

// Get all categories for sidebar
$categories = getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="TemplateMo">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">

    <title>NetPy Blog - Home Page</title>

    <!-- Bootstrap core CSS -->
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Additional CSS Files -->
    <link rel="stylesheet" href="assets/css/fontawesome.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="assets/css/owl.css">
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

    <!-- Page Content -->
    <!-- Banner Starts Here -->
    <div class="main-banner header-text">
        <div class="container-fluid">
            <div class="owl-banner owl-carousel">
                <?php foreach ($featured_posts as $post): ?>
                <div class="item">
                    <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                    <div class="item-content">
                        <div class="main-content">
                            <div class="meta-category">
                                <span><?php echo htmlspecialchars($post['category_name']); ?></span>
                            </div>
                            <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>">
                                <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                            </a>
                            <ul class="post-info">
                                <li><a href="#"><?php echo htmlspecialchars($post['author_name']); ?></a></li>
                                <li><a href="#"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></a></li>
                                <li><a href="#"><?php echo $post['views']; ?> Views</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- Banner Ends Here -->

    <section class="blog-posts">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="all-blog-posts">
                        <div class="row">
                            <?php foreach ($posts as $post): ?>
                            <div class="col-lg-12">
                                <div class="blog-post">
                                    <div class="blog-thumb">
                                        <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                    </div>
                                    <div class="down-content">
                                        <span><?php echo htmlspecialchars($post['category_name']); ?></span>
                                        <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>">
                                            <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                        </a>
                                        <ul class="post-info">
                                            <li><a href="#"><?php echo htmlspecialchars($post['author_name']); ?></a></li>
                                            <li><a href="#"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></a></li>
                                            <li><a href="#"><?php echo $post['views']; ?> Views</a></li>
                                        </ul>
                                        <div class="post-preview">
                                            <?php 
                                            $preview = strip_tags($post['content']);
                                            echo strlen($preview) > 200 ? substr($preview, 0, 200) . '...' : $preview;
                                            ?>
                                        </div>
                                        <div class="post-options">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <ul class="post-tags">
                                                        <li><i class="fa fa-tags"></i></li>
                                                        <?php
                                                        // Get post tags
                                                        $sql = "SELECT t.name, t.slug 
                                                                FROM tags t 
                                                                JOIN post_tags pt ON t.id = pt.tag_id 
                                                                WHERE pt.post_id = ?";
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->bind_param("i", $post['id']);
                                                        $stmt->execute();
                                                        $post_tags = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                                        
                                                        if (!empty($post_tags)) {
                                                            foreach ($post_tags as $index => $tag) {
                                                                echo '<li><a href="tag.php?slug=' . urlencode($tag['slug']) . '">' . 
                                                                     htmlspecialchars($tag['name']) . '</a>';
                                                                if ($index < count($post_tags) - 1) {
                                                                    echo ', ';
                                                                }
                                                                echo '</li>';
                                                            }
                                                        } else {
                                                            echo '<li>No tags</li>';
                                                        }
                                                        ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                            <div class="pagination justify-content-center mt-4">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1" aria-label="First">
                                                    <span aria-hidden="true">&laquo;&laquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page-1; ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);

                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page+1; ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $total_pages; ?>" aria-label="Last">
                                                    <span aria-hidden="true">&raquo;&raquo;</span>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <?php include 'includes/sidebar.php'; ?>
            </div>
        </div>
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

    <script>
        $(document).ready(function() {
            var owl = $('.owl-banner');
            owl.owlCarousel({
                items: 3,
                loop: true,
                dots: true,
                nav: true,
                navText: [
                    '&#x2190;',
                    '&#x2192;'
                ],
                autoplay: true,
                autoplayTimeout: 5000,
                autoplayHoverPause: true,
                margin: 30,
                responsive: {
                    0: {
                        items: 1,
                        nav: true
                    },
                    600: {
                        items: 2,
                        nav: true
                    },
                    1000: {
                        items: 3,
                        nav: true
                    }
                }
            });
        });
    </script>

    <style>
        .owl-banner .owl-nav {
            position: absolute;
            top: 50%;
            width: 100%;
            transform: translateY(-50%);
            z-index: 10;
            display: block !important;
        }

        .owl-banner .owl-nav button {
            position: absolute;
            width: 45px;
            height: 45px;
            background: #0047cc !important;
            border-radius: 50% !important;
            outline: none;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: flex !important;
            align-items: center;
            justify-content: center;
            padding: 0 !important;
            color: #fff !important;
            font-size: 28px !important;
            font-weight: bold;
            border: none !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .owl-banner .owl-nav button:hover {
            background: #0047cc !important;
            opacity: 0.8 !important;
            box-shadow: 0 3px 8px rgba(0,0,0,0.3);
        }

        .owl-banner .owl-nav button span {
            background: transparent !important;
            display: block !important;
            width: auto !important;
            height: auto !important;
            border: none !important;
            font-size: inherit !important;
            color: inherit !important;
            line-height: 1 !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .owl-banner .owl-nav .owl-prev {
            left: 10px;
        }

        .owl-banner .owl-nav .owl-next {
            right: 10px;
        }

        @media (max-width: 768px) {
            .owl-banner .owl-nav {
                display: block !important;
            }
            .owl-banner .owl-nav button {
                width: 40px;
                height: 40px;
                font-size: 24px !important;
                display: flex !important;
            }
            .owl-banner .owl-nav .owl-prev {
                left: 5px;
            }
            .owl-banner .owl-nav .owl-next {
                right: 5px;
            }
        }

        /* Ensure dots are visible */
        .owl-banner .owl-dots {
            position: absolute;
            bottom: 15px;
            width: 100%;
            text-align: center;
            display: block !important;
        }

        .owl-banner .owl-dots .owl-dot {
            display: inline-block;
            margin: 0 5px;
        }

        .owl-banner .owl-dots .owl-dot span {
            width: 12px;
            height: 12px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: inline-block;
            transition: all 0.3s;
        }

        .owl-banner .owl-dots .owl-dot.active span {
            background: #fff;
        }

        /* Add styles for post info in slider */
        .owl-banner .item .item-content .post-info {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        .owl-banner .item .item-content .post-info li {
            display: inline-block;
            margin-right: 8px;
        }

        .owl-banner .item .item-content .post-info li:after {
            content: '|';
            color: #fff;
            margin-left: 8px;
        }

        .owl-banner .item .item-content .post-info li:last-child:after {
            display: none;
        }

        .owl-banner .item .item-content .post-info li a {
            font-size: 14px;
            color: #fff;
            font-weight: 500;
            transition: all .3s;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
        }

        .owl-banner .item .item-content .post-info li a:hover {
            color: #0047cc;
        }

        .blog-post {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: box-shadow 0.3s ease;
            margin-bottom: 30px;
            border-radius: 20px;
            overflow: hidden;
            background-color: #fff;
        }
        
        .blog-post:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .blog-post .blog-thumb img {
            border-radius: 20px 20px 0 0;
        }

        .down-content {
            background-color: #fff;
        }

        .down-content span {
            color: #0047cc !important;
        }

        .down-content h4 {
            color: #333333;
        }

        .down-content ul.post-info li a {
            color: #333333;
        }

        .down-content ul.post-info li a:hover {
            color: #0047cc;
        }

        .post-options ul.post-tags li a {
            color: #333333;
        }

        .post-options ul.post-tags li a:hover {
            color: #0047cc;
        }

        .sidebar-item .content ul li a {
            color: #333333;
        }

        .sidebar-item .content ul li a:hover {
            color: #0047cc;
        }

        .sidebar-heading h2 {
            color: #333333;
        }

        .pagination .page-item.active .page-link {
            background-color: #0047cc;
            border-color: #0047cc;
        }

        .pagination .page-link {
            color: #0047cc;
        }

        .pagination .page-link:hover {
            background-color: #0047cc;
            color: #fff;
        }
    </style>
</body>
</html> 