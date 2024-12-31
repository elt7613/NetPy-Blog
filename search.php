<?php
require_once 'config.php';
require_once 'functions.php';

// Get search query
$query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';

// Get current page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$posts_per_page = 10;
$offset = ($page - 1) * $posts_per_page;

// Get total number of search results
$count_sql = "SELECT COUNT(DISTINCT p.id) as total 
              FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN users u ON p.author_id = u.id 
              LEFT JOIN post_tags pt ON p.id = pt.post_id
              LEFT JOIN tags t ON pt.tag_id = t.id
              WHERE p.status = 'published' AND (
                  p.title LIKE ? OR 
                  p.content LIKE ? OR 
                  c.name LIKE ? OR 
                  t.name LIKE ?
              )";

$search_param = "%$query%";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
$count_stmt->execute();
$total_posts = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Ensure current page is within valid range
$page = max(1, min($page, $total_pages));

// Get search results with pagination
$sql = "SELECT DISTINCT p.*, c.name as category_name, u.username as author_name,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.author_id = u.id 
        LEFT JOIN post_tags pt ON p.id = pt.post_id
        LEFT JOIN tags t ON pt.tag_id = t.id
        WHERE p.status = 'published' AND (
            p.title LIKE ? OR 
            p.content LIKE ? OR 
            c.name LIKE ? OR 
            t.name LIKE ?
        )
        GROUP BY p.id 
        ORDER BY p.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssii", $search_param, $search_param, $search_param, $search_param, $posts_per_page, $offset);
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
    <meta name="description" content="Search results for <?php echo htmlspecialchars($query); ?>">
    <meta name="author" content="">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">

    <title>Search Results - Stand Blog</title>

    <!-- Bootstrap core CSS -->
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Additional CSS Files -->
    <link rel="stylesheet" href="assets/css/fontawesome.css">
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
    <div class="heading-page header-text">
        <section class="page-heading">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-content">
                            <h4>Search Results</h4>
                            <h2>Found <?php echo count($posts); ?> results for "<?php echo htmlspecialchars($query); ?>"</h2>
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
                            <?php if (empty($posts)): ?>
                            <div class="col-lg-12">
                                <div class="blog-post">
                                    <div class="down-content">
                                        <p>No results found for your search query. Please try different keywords.</p>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
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
                                            <p><?php echo substr(strip_tags($post['content']), 0, 200) . '...'; ?></p>
                                            <div class="post-options">
                                                <div class="row">
                                                    <div class="col-12">
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
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
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
</body>
</html> 