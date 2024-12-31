<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_by = isset($_GET['search_by']) ? $_GET['search_by'] : 'title';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Get current page number
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$posts_per_page = 10;
$offset = ($page - 1) * $posts_per_page;

// Get total number of posts (for pagination)
$count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN users u ON p.author_id = u.id 
              LEFT JOIN post_tags pt ON p.id = pt.post_id
              LEFT JOIN tags t ON pt.tag_id = t.id
              WHERE 1=1";

// Add search conditions to count query
if (!empty($search)) {
    switch ($search_by) {
        case 'title':
            $count_sql .= " AND p.title LIKE ?";
            $search_param = "%$search%";
            break;
        case 'author':
            $count_sql .= " AND u.username LIKE ?";
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

// Bind parameters for count query
if (!empty($search) && !empty($status_filter) && !empty($date_from) && !empty($date_to)) {
    $count_stmt->bind_param("ssss", $search_param, $date_from, $date_to, $status_filter);
} elseif (!empty($search) && !empty($date_from) && !empty($date_to)) {
    $count_stmt->bind_param("sss", $search_param, $date_from, $date_to);
} elseif (!empty($search) && !empty($status_filter)) {
    $count_stmt->bind_param("ss", $search_param, $status_filter);
} elseif (!empty($date_from) && !empty($date_to) && !empty($status_filter)) {
    $count_stmt->bind_param("sss", $date_from, $date_to, $status_filter);
} elseif (!empty($search)) {
    $count_stmt->bind_param("s", $search_param);
} elseif (!empty($date_from) && !empty($date_to)) {
    $count_stmt->bind_param("ss", $date_from, $date_to);
} elseif (!empty($status_filter)) {
    $count_stmt->bind_param("s", $status_filter);
}

$count_stmt->execute();
$total_posts = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $posts_per_page);

// Ensure current page is within valid range
$page = max(1, min($page, $total_pages));

// Get post statistics
$sql = "SELECT 
            COUNT(*) as total_posts,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_posts,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_posts,
            SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured_posts
        FROM posts";
$stats = $conn->query($sql)->fetch_assoc();

// Get user statistics
$sql = "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
            SUM(CASE WHEN role = 'author' THEN 1 ELSE 0 END) as author_users,
            SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as normal_users
        FROM users";
$user_stats = $conn->query($sql)->fetch_assoc();

// Get recent posts with search
$sql = "SELECT DISTINCT p.*, c.name as category_name, u.username as author_name,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.author_id = u.id 
        LEFT JOIN post_tags pt ON p.id = pt.post_id
        LEFT JOIN tags t ON pt.tag_id = t.id
        WHERE 1=1";

// Add search conditions
if (!empty($search)) {
    switch ($search_by) {
        case 'title':
            $sql .= " AND p.title LIKE ?";
            $search_param = "%$search%";
            break;
        case 'author':
            $sql .= " AND u.username LIKE ?";
            $search_param = "%$search%";
            break;
        case 'category':
            $sql .= " AND c.name LIKE ?";
            $search_param = "%$search%";
            break;
        case 'tag':
            $sql .= " AND t.name LIKE ?";
            $search_param = "%$search%";
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

// Bind parameters based on filters with LIMIT and OFFSET
if (!empty($search) && !empty($status_filter) && !empty($date_from) && !empty($date_to)) {
    $stmt->bind_param("ssssii", $search_param, $date_from, $date_to, $status_filter, $posts_per_page, $offset);
} elseif (!empty($search) && !empty($date_from) && !empty($date_to)) {
    $stmt->bind_param("sssii", $search_param, $date_from, $date_to, $posts_per_page, $offset);
} elseif (!empty($search) && !empty($status_filter)) {
    $stmt->bind_param("ssii", $search_param, $status_filter, $posts_per_page, $offset);
} elseif (!empty($date_from) && !empty($date_to) && !empty($status_filter)) {
    $stmt->bind_param("sssii", $date_from, $date_to, $status_filter, $posts_per_page, $offset);
} elseif (!empty($search)) {
    $stmt->bind_param("sii", $search_param, $posts_per_page, $offset);
} elseif (!empty($date_from) && !empty($date_to)) {
    $stmt->bind_param("ssii", $date_from, $date_to, $posts_per_page, $offset);
} elseif (!empty($status_filter)) {
    $stmt->bind_param("sii", $status_filter, $posts_per_page, $offset);
} else {
    $stmt->bind_param("ii", $posts_per_page, $offset);
}

$stmt->execute();
$recent_posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total posts count
$total_posts_sql = "SELECT COUNT(*) as total FROM posts";
$total_posts = $conn->query($total_posts_sql)->fetch_assoc()['total'];

// Get published posts count
$published_posts_sql = "SELECT COUNT(*) as total FROM posts WHERE status = 'published'";
$published_posts = $conn->query($published_posts_sql)->fetch_assoc()['total'];

// Get draft posts count
$draft_posts_sql = "SELECT COUNT(*) as total FROM posts WHERE status = 'draft'";
$draft_posts = $conn->query($draft_posts_sql)->fetch_assoc()['total'];

// Get total comments count
$total_comments_sql = "SELECT COUNT(*) as total FROM comments";
$total_comments = $conn->query($total_comments_sql)->fetch_assoc()['total'];

// Get recent posts
$recent_posts_sql = "SELECT p.*, c.name as category_name, u.username as author_name 
                     FROM posts p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN users u ON p.author_id = u.id 
                     ORDER BY p.created_at DESC 
                     LIMIT 10";
$recent_posts = $conn->query($recent_posts_sql)->fetch_all(MYSQLI_ASSOC);

// Get recent comments
$recent_comments_sql = "SELECT c.*, p.title as post_title, u.username 
                       FROM comments c 
                       LEFT JOIN posts p ON c.post_id = p.id 
                       LEFT JOIN users u ON c.user_id = u.id 
                       ORDER BY c.created_at DESC 
                       LIMIT 5";
$recent_comments = $conn->query($recent_comments_sql)->fetch_all(MYSQLI_ASSOC);

$page_title = "Dashboard";
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard - Stand Blog</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">
    <style>
        .admin-menu {
            background: #f7f7f7;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .admin-menu .btn {
            margin: 5px;
        }
        .stats-box {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-box h3 {
            margin-bottom: 15px;
            color: #f48840;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Page Content -->
    <div class="heading-page header-text">
        <section class="page-heading">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-content">
                            <h4>Admin</h4>
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
                    <!-- Admin Menu -->
                    <div class="admin-menu">
                        <a href="new-post.php" class="btn btn-primary">New Post</a>
                        <a href="manage-users.php" class="btn btn-info">Manage Users</a>
                        <a href="manage-categories.php" class="btn btn-success">Manage Categories</a>
                        <a href="manage-tags.php" class="btn btn-secondary">Manage Tags</a>
                        <a href="settings.php" class="btn btn-dark">Settings</a>
                    </div>

                    <!-- Statistics -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>Total Posts</h3>
                                <p class="h4"><?php echo $stats['total_posts']; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>Published Posts</h3>
                                <p class="h4"><?php echo $stats['published_posts']; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
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
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>Total Users</h3>
                                <p class="h4"><?php echo $user_stats['total_users']; ?></p>
                                <small>
                                    Admins: <?php echo $user_stats['admin_users']; ?> |
                                    Authors: <?php echo $user_stats['author_users']; ?> |
                                    Users: <?php echo $user_stats['normal_users']; ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Posts -->
                    <div class="all-blog-posts">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="sidebar-item recent-posts">
                                    <div class="sidebar-heading">
                                        <h2>Recent Posts</h2>
                                    </div>
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
                                                        <option value="author" <?php echo $search_by === 'author' ? 'selected' : ''; ?>>Author</option>
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
                                                <div class="col-md-1">
                                                    <button type="submit" class="btn btn-primary">Search</button>
                                                    <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                                                        <a href="dashboard.php" class="btn btn-secondary mt-2">Clear</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="content">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Author</th>
                                                    <th>Category</th>
                                                    <th>Tags</th>
                                                    <th>Status</th>
                                                    <th>Featured</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_posts as $post): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            <?php echo htmlspecialchars($post['author_name']); ?>
                                                        </span>
                                                    </td>
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
                                                        <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                        <a href="../post-details.php?slug=<?php echo $post['slug']; ?>" class="btn btn-sm btn-info" target="_blank">View</a>
                                                        <form action="delete-post.php" method="post" style="display: inline;">
                                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                                                        </form>
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
</body>
</html> 