<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit();
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_by = isset($_GET['search_by']) ? $_GET['search_by'] : 'title';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$active_filter = isset($_GET['active']) ? $_GET['active'] : '';
$show_deleted = isset($_GET['show_deleted']) ? $_GET['show_deleted'] : '0';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get author's post statistics
$sql = "SELECT 
            COUNT(*) as total_posts,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_posts,
            SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured_posts
        FROM posts WHERE author_id = ? AND deleted_at IS NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get current page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$posts_per_page = 10;
$offset = ($page - 1) * $posts_per_page;

// Get total number of posts (for pagination)
$count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN post_tags pt ON p.id = pt.post_id
              LEFT JOIN tags t ON pt.tag_id = t.id
              WHERE p.author_id = ? AND " . ($show_deleted === '1' ? "p.deleted_at IS NOT NULL" : "p.deleted_at IS NULL");

// Add active/inactive filter
if ($active_filter !== '') {
    $count_sql .= " AND p.is_active = ?";
}

// Add search conditions to count query
if (!empty($search)) {
    switch ($search_by) {
        case 'title':
            $count_sql .= " AND p.title LIKE ?";
            $search_param = "%$search%";
            break;
        case 'category':
            $count_sql .= " AND c.name LIKE ?";
            $search_param = "%$search%";
            break;
        case 'tag':
            $count_sql .= " AND t.name LIKE ?";
            $search_param = "%$search%";
            break;
    }
}

if (!empty($date_from) && !empty($date_to)) {
    $count_sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
}

if (!empty($status_filter)) {
    $count_sql .= " AND p.status = ?";
}

$count_stmt = $conn->prepare($count_sql);

// Build parameter array and types string for count query
$count_params = array($_SESSION['user_id']);
$count_types = "i";

if ($active_filter !== '') {
    $count_params[] = $active_filter;
    $count_types .= "i";
}

if (!empty($search)) {
    $count_params[] = $search_param;
    $count_types .= "s";
}

if (!empty($date_from) && !empty($date_to)) {
    $count_params[] = $date_from;
    $count_params[] = $date_to;
    $count_types .= "ss";
}

if (!empty($status_filter)) {
    $count_params[] = $status_filter;
    $count_types .= "s";
}

// Bind parameters dynamically for count query
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$total_posts = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Ensure current page is within valid range
$page = max(1, min($page, max(1, $total_pages)));

// Modify the main query to include LIMIT and OFFSET
$sql = "SELECT DISTINCT p.*, c.name as category_name,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN post_tags pt ON p.id = pt.post_id
        LEFT JOIN tags t ON pt.tag_id = t.id
        WHERE p.author_id = ? AND " . ($show_deleted === '1' ? "p.deleted_at IS NOT NULL" : "p.deleted_at IS NULL");

// Add active/inactive filter
if ($active_filter !== '') {
    $sql .= " AND p.is_active = ?";
}

// Add search conditions
if (!empty($search)) {
    switch ($search_by) {
        case 'title':
            $sql .= " AND p.title LIKE ?";
            break;
        case 'category':
            $sql .= " AND c.name LIKE ?";
            break;
        case 'tag':
            $sql .= " AND t.name LIKE ?";
            break;
    }
}

// Add date range filter
if (!empty($date_from) && !empty($date_to)) {
    $sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
}

// Add status filter
if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
}

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

// Build parameter array and types string for main query
$params = array($_SESSION['user_id']);
$types = "i";

if ($active_filter !== '') {
    $params[] = $active_filter;
    $types .= "i";
}

if (!empty($search)) {
    $params[] = $search_param;
    $types .= "s";
}

if (!empty($date_from) && !empty($date_to)) {
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= "ss";
}

if (!empty($status_filter)) {
    $params[] = $status_filter;
    $types .= "s";
}

// Add LIMIT and OFFSET parameters
$params[] = $posts_per_page;
$params[] = $offset;
$types .= "ii";

// Bind parameters dynamically for main query
$stmt->bind_param($types, ...$params);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// For debugging pagination issues
if (empty($posts) && $page > 1) {
    error_log("Debug - Page: $page, Total Pages: $total_pages, Offset: $offset, Total Posts: $total_posts");
    error_log("Debug - SQL: $sql");
    error_log("Debug - Params: " . print_r($params, true));
}

$page_title = "Author Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $page_title; ?> - NetPy Blog</title>

    <!-- Bootstrap & Core CSS -->
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">

    <style>
        .blog-posts {
            margin-top: 30px;
            padding: 30px 0;
            background-color: #f7f7f7;
        }
        .card {
            border: none;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-bottom: 30px;
            background-color: #fff;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px 30px;
            border-radius: 10px 10px 0 0;
        }
        .card-header h4 {
            margin: 0;
            color: #20232e;
            font-weight: 700;
        }
        .card-body {
            padding: 30px;
        }
        .stats-box {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        .stats-box:hover {
            transform: translateY(-5px);
        }
        .stats-box h3 {
            margin-bottom: 15px;
            color: #f48840;
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .stats-box .h4 {
            color: #20232e;
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }
        .admin-menu {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .admin-menu .btn {
            margin: 5px;
            padding: 10px 25px;
            font-weight: 500;
        }
        .btn-primary {
            background-color: #f48840;
            border-color: #f48840;
        }
        .btn-primary:hover {
            background-color: #d67736;
            border-color: #d67736;
        }
        .table {
            margin-bottom: 0;
            background-color: #fff;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #20232e;
            text-transform: uppercase;
            font-size: 14px;
            padding: 15px;
            background-color: #f8f9fa;
        }
        .table td {
            vertical-align: middle;
            padding: 15px;
            border-color: #eee;
        }
        .badge {
            padding: 8px 12px;
            font-weight: 500;
            border-radius: 4px;
        }
        .badge-success {
            background-color: #28a745;
            color: #fff;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            margin: 0 2px;
        }
        .heading-page {
            background-color: #f7f7f7;
            padding: 40px 0;
            text-align: center;
            margin-bottom: 0;
            border-bottom: 1px solid #eee;
        }
        .heading-page h4 {
            color: #f48840;
            font-size: 18px;
            text-transform: uppercase;
            font-weight: 900;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
        }
        .heading-page h2 {
            color: #20232e;
            font-size: 36px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .alert {
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert ul {
            padding-left: 20px;
            margin-bottom: 0;
        }
        #preloader {
            background-color: #fff;
        }
    </style>
</head>

<body>
    <!-- Preloader -->
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Page Content -->
    <div class="heading-page header-text">
        <section class="page-heading">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-content">
                            <h4>Author</h4>
                            <h2>Dashboard</h2>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="blog-posts">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <!-- Author Menu -->
                    <div class="admin-menu text-center mb-4">
                        <a href="<?php echo $base_url; ?>author/new-post.php" class="btn btn-primary">Create New Post</a>
                        <a href="<?php echo $base_url; ?>user-settings.php" class="btn btn-secondary">Settings</a>
                    </div>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="stats-box">
                                <h3>Total Posts</h3>
                                <p class="h4"><?php echo $stats['total_posts']; ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-box">
                                <h3>Published Posts</h3>
                                <p class="h4"><?php echo $stats['published_posts']; ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-box">
                                <h3>Draft Posts</h3>
                                <p class="h4"><?php echo $stats['draft_posts']; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>Featured Posts</h3>
                                <div class="h4"><?php echo $stats['featured_posts']; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- After the existing statistics cards -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Category Management</h5>
                                    <p class="card-text">Create and manage categories for your blog posts.</p>
                                    <a href="manage-categories.php" class="btn btn-primary">Manage Categories</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Tag Management</h5>
                                    <p class="card-text">Create and manage tags to organize your content.</p>
                                    <a href="manage-tags.php" class="btn btn-primary">Manage Tags</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Posts -->
                    <div class="card">
                        <div class="card-header">
                            <h4>Your Posts</h4>
                        </div>
                        <div class="card-body">
                            <!-- Search Form -->
                            <div class="mb-4">
                                <form method="GET" class="form-inline">
                                    <div class="row w-100">
                                        <div class="col-md-3">
                                            <input type="text" name="search" class="form-control" placeholder="Search posts..." value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <select name="search_by" class="form-control">
                                                <option value="title" <?php echo $search_by === 'title' ? 'selected' : ''; ?>>Title</option>
                                                <option value="category" <?php echo $search_by === 'category' ? 'selected' : ''; ?>>Category</option>
                                                <option value="tag" <?php echo $search_by === 'tag' ? 'selected' : ''; ?>>Tag</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="status" class="form-control">
                                                <option value="">All Status</option>
                                                <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <select name="active" class="form-control">
                                                <option value="">All Posts</option>
                                                <option value="1" <?php echo $active_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                                <option value="0" <?php echo $active_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="show_deleted" class="form-control">
                                                <option value="0" <?php echo $show_deleted === '0' ? 'selected' : ''; ?>>Active Posts</option>
                                                <option value="1" <?php echo $show_deleted === '1' ? 'selected' : ''; ?>>Deleted Posts</option>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary">Search</button>
                                            <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                                                <a href="dashboard.php" class="btn btn-secondary mt-2">Clear</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Tags</th>
                                            <th>Status</th>
                                            <th>Featured</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($posts as $post): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($post['title']); ?></td>
                                            <td><?php echo htmlspecialchars($post['category_name']); ?></td>
                                            <td>
                                                <?php if (!empty($post['post_tags'])): ?>
                                                    <?php foreach (explode(', ', $post['post_tags']) as $tag): ?>
                                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($tag); ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No tags</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $post['status'] === 'published' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($post['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($post['featured']): ?>
                                                    <span class="badge badge-primary">Featured</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($post['created_at'])); ?></td>
                                            <td>
                                                <?php if ($show_deleted === '0'): ?>
                                                    <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                                    <a href="../post-details.php?slug=<?php echo $post['slug']; ?>" class="btn btn-info btn-sm" target="_blank">View</a>
                                                    <form method="POST" action="delete-post.php" style="display: inline;">
                                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $post['is_active'] ? '0' : '1'; ?>">
                                                        <button type="submit" name="toggle_status" class="btn btn-<?php echo $post['is_active'] ? 'warning' : 'success'; ?> btn-sm">
                                                            <?php echo $post['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                        <button type="submit" name="delete_post" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this post?');">
                                                            Delete
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Deleted</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="pagination justify-content-center mt-4">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=1<?php 
                                                        echo !empty($search) ? '&search='.urlencode($search) : '';
                                                        echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                        echo !empty($status_filter) ? '&status='.urlencode($status_filter) : '';
                                                        echo !empty($active_filter) ? '&active='.urlencode($active_filter) : '';
                                                        echo !empty($show_deleted) ? '&show_deleted='.urlencode($show_deleted) : '';
                                                        echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                        echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                    ?>" aria-label="First">
                                                        <span aria-hidden="true">&laquo;&laquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php 
                                                        echo !empty($search) ? '&search='.urlencode($search) : '';
                                                        echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                        echo !empty($status_filter) ? '&status='.urlencode($status_filter) : '';
                                                        echo !empty($active_filter) ? '&active='.urlencode($active_filter) : '';
                                                        echo !empty($show_deleted) ? '&show_deleted='.urlencode($show_deleted) : '';
                                                        echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                        echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                    ?>" aria-label="Previous">
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
                                                    <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                                        echo !empty($search) ? '&search='.urlencode($search) : '';
                                                        echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                        echo !empty($status_filter) ? '&status='.urlencode($status_filter) : '';
                                                        echo !empty($active_filter) ? '&active='.urlencode($active_filter) : '';
                                                        echo !empty($show_deleted) ? '&show_deleted='.urlencode($show_deleted) : '';
                                                        echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                        echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                    ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php 
                                                        echo !empty($search) ? '&search='.urlencode($search) : '';
                                                        echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                        echo !empty($status_filter) ? '&status='.urlencode($status_filter) : '';
                                                        echo !empty($active_filter) ? '&active='.urlencode($active_filter) : '';
                                                        echo !empty($show_deleted) ? '&show_deleted='.urlencode($show_deleted) : '';
                                                        echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                        echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                    ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $total_pages; ?><?php 
                                                        echo !empty($search) ? '&search='.urlencode($search) : '';
                                                        echo !empty($search_by) ? '&search_by='.urlencode($search_by) : '';
                                                        echo !empty($status_filter) ? '&status='.urlencode($status_filter) : '';
                                                        echo !empty($active_filter) ? '&active='.urlencode($active_filter) : '';
                                                        echo !empty($show_deleted) ? '&show_deleted='.urlencode($show_deleted) : '';
                                                        echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                        echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                    ?>" aria-label="Last">
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
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/custom.js"></script>
    <script src="../assets/js/owl.js"></script>
    <script src="../assets/js/slick.js"></script>
    <script src="../assets/js/isotope.js"></script>
    <script src="../assets/js/accordions.js"></script>

    <script>
        // Hide preloader when page is loaded
        window.addEventListener('load', function() {
            document.getElementById('preloader').style.display = 'none';
        });
    </script>
</body>
</html> 