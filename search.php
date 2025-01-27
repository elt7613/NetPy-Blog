<?php
require_once 'config.php';
require_once 'functions.php';

// Get search query
$query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
$error = null;
$total_posts = 0;
$total_pages = 0;
$posts = array();

// Validate query length and content
if (empty($query)) {
    $error = "Please enter a search term.";
} else {
    // Get current page number
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $posts_per_page = 10;
    $offset = ($page - 1) * $posts_per_page;

    try {
        // Get total number of matching posts
        $count_sql = "SELECT COUNT(DISTINCT p.id) as total 
                  FROM posts p
                  LEFT JOIN categories c ON p.category_id = c.id 
                  LEFT JOIN users u ON p.author_id = u.id 
                  LEFT JOIN post_tags pt ON p.id = pt.post_id
                  LEFT JOIN tags t ON pt.tag_id = t.id
                  WHERE p.status = 'published'
                  AND p.deleted_at IS NULL 
                  AND p.is_active = 1
                  AND (c.id IS NULL OR (c.deleted_at IS NULL AND c.is_active = 1))
                  AND (u.id IS NULL OR (u.deleted_at IS NULL AND u.is_active = 1))
                  AND (t.id IS NULL OR (t.deleted_at IS NULL AND t.is_active = 1))
                  AND (
                      p.title LIKE ? 
                      OR p.content LIKE ? 
                      OR c.name LIKE ? 
                      OR u.username LIKE ?
                      OR t.name LIKE ?
                  )";
        $stmt = $conn->prepare($count_sql);
        $search_param = "%$query%";
        $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $search_param);
        $stmt->execute();
        $total_posts = $stmt->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_posts / $posts_per_page);
        $stmt->close();

        // Ensure current page is within valid range
        $page = max(1, min($page, $total_pages));

        if ($total_posts > 0) {
            // Get search results with pagination
            $sql = "SELECT p.*, c.name as category_name, u.username as author_name,
                    GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags 
                    FROM posts p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    LEFT JOIN users u ON p.author_id = u.id 
                    LEFT JOIN post_tags pt ON p.id = pt.post_id
                    LEFT JOIN tags t ON pt.tag_id = t.id
                    WHERE p.status = 'published'
                    AND p.deleted_at IS NULL 
                    AND p.is_active = 1
                    AND (c.id IS NULL OR (c.deleted_at IS NULL AND c.is_active = 1))
                    AND (u.id IS NULL OR (u.deleted_at IS NULL AND u.is_active = 1))
                    AND (t.id IS NULL OR (t.deleted_at IS NULL AND t.is_active = 1))
                    AND (
                        p.title LIKE ? 
                        OR p.content LIKE ? 
                        OR c.name LIKE ? 
                        OR u.username LIKE ?
                        OR t.name LIKE ?
                    )
                    GROUP BY p.id 
                    ORDER BY p.created_at DESC 
                    LIMIT ? OFFSET ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssii", $search_param, $search_param, $search_param, $search_param, $search_param, $posts_per_page, $offset);
            $stmt->execute();
            $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        $error = "An error occurred while performing the search. Please try again.";
    }
}

// Get categories for sidebar
$categories = getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Search results for <?php echo htmlspecialchars($query); ?>">
    <meta name="author" content="">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">

    <title>Search Results - NetPy Blog</title>

    <!-- Bootstrap core CSS -->
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Additional CSS Files -->
    <link rel="stylesheet" href="assets/css/fontawesome.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="assets/css/owl.css">
    
    <style>
        @media (min-width: 992px) {
            .blog-posts .col-lg-4 {
                padding-left: 0px;
            }
        }

        .blog-post {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: box-shadow 0.3s ease;
            margin-bottom: 20px;
            border-radius: 15px;
            overflow: hidden;
            background-color: #fff;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .blog-post:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .blog-post .blog-thumb {
            position: relative;
            overflow: hidden;
        }

        .blog-post .blog-thumb a {
            display: block;
        }

        .blog-post .blog-thumb img {
            border-radius: 15px 15px 0 0;
            max-height: 300px;
            width: 100%;
            object-fit: cover;
        }

        .blog-post .blog-thumb:hover img {
            transform: scale(1.05);
        }

        .read-more {
            display: inline-flex;
            align-items: center;
            color: #181818;
            font-weight: 500;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .read-more i {
            margin-left: 5px;
            transition: transform 0.3s ease;
            font-size: 12px;
        }

        .read-more:hover {
            color: #0047cc;
            text-decoration: none;
        }

        .read-more:hover i {
            transform: translateX(5px);
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

    <!-- Page Content -->
    <!-- Banner Starts Here -->
    <div class="heading-page header-text">
        <section class="page-heading">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-content">
                            <h4>Search Results</h4>
                            <?php if ($error): ?>
                                <h2><?php echo htmlspecialchars($error); ?></h2>
                            <?php else: ?>
                                <h2>Found <?php echo $total_posts; ?> results for "<?php echo htmlspecialchars($query); ?>"</h2>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="blog-posts grid-system">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="all-blog-posts">
                        <div class="row">
                            <?php if ($error || empty($posts)): ?>
                            <div class="col-lg-12">
                                <div class="blog-post">
                                    <div class="down-content">
                                        <?php if ($error): ?>
                                            <p><?php echo htmlspecialchars($error); ?></p>
                                        <?php else: ?>
                                            <p>No results found for your search query. Please try different keywords.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                                <?php foreach ($posts as $post): ?>
                                <div class="col-lg-12">
                                    <div class="blog-post">
                                        <div class="blog-thumb">
                                            <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>">
                                                <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                            </a>
                                        </div>
                                        <div class="down-content">
                                            <span style="font-size: 9px; margin-bottom: 0px;"><?php echo htmlspecialchars($post['category_name']); ?></span>
                                            <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>">
                                                <h4 style="font-size: 1.3rem; margin-top: 5px; margin-bottom: 0px;"><?php echo htmlspecialchars($post['title']); ?></h4>
                                            </a>
                                            <ul class="post-info">
                                                <li><a href="#" style="font-size: 10px;"><?php echo htmlspecialchars($post['author_name']); ?></a></li>
                                                <li><a href="#" style="font-size: 10px;"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></a></li>
                                                <li><a href="#" style="font-size: 10px;"><?php echo $post['views']; ?> Views</a></li>
                                            </ul>
                                            <div class="post-preview"  style="font-size: 0.9rem; color: #181818; margin-top: 5px;">
                                                <?php 
                                                $preview = strip_tags($post['content']);
                                                echo strlen($preview) > 200 ? substr($preview, 0, 200) . '...' : $preview;
                                                ?>
                                            </div>
                                            <div class="post-options">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <ul class="post-tags">
                                                            <li><i class="fa fa-tags"></i></li>
                                                            <?php
                                                            if (!empty($post['post_tags'])) {
                                                                $tags = explode(', ', $post['post_tags']);
                                                                foreach ($tags as $index => $tag) {
                                                                    echo '<li><a href="tag.php?slug=' . urlencode(createSlug($tag)) . '">' . 
                                                                         htmlspecialchars($tag) . '</a>';
                                                                    if ($index < count($tags) - 1) {
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
                                                    <div class="col-6 text-right">
                                                        <a href="post-details.php?slug=<?php echo urlencode($post['slug']); ?>" class="read-more">Read More <i class="fa fa-arrow-right"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=1" aria-label="First">
                                            <span aria-hidden="true">&laquo;&laquo;</span>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=<?php echo $page-1; ?>" aria-label="Previous">
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
                                        <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=<?php echo $page+1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?q=<?php echo urlencode($query); ?>&page=<?php echo $total_pages; ?>" aria-label="Last">
                                            <span aria-hidden="true">&raquo;&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
                
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
</body>
</html> 