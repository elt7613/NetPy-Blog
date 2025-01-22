<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get search parameters and sanitize them
$search = isset($_GET['search']) ? trim(htmlspecialchars($_GET['search'])) : '';
$search_by = isset($_GET['search_by']) && in_array($_GET['search_by'], ['title', 'author', 'category', 'tag']) ? $_GET['search_by'] : 'title';
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['published', 'draft']) ? $_GET['status'] : '';
$active_filter = isset($_GET['active']) && in_array($_GET['active'], ['0', '1']) ? $_GET['active'] : '';
$sort_order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['asc', 'desc']) ? $_GET['sort_order'] : 'desc';

// Get current page number and validate
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$posts_per_page = 10;
$offset = ($page - 1) * $posts_per_page;

// Validate and format date inputs
$date_from = isset($_GET['date_from']) && strtotime($_GET['date_from']) ? date('Y-m-d', strtotime($_GET['date_from'])) : '';
$date_to = isset($_GET['date_to']) && strtotime($_GET['date_to']) ? date('Y-m-d', strtotime($_GET['date_to'])) : '';

// Base SQL for counting and fetching posts
$base_sql = "FROM posts p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN users u ON p.author_id = u.id 
              LEFT JOIN post_tags pt ON p.id = pt.post_id
              LEFT JOIN tags t ON pt.tag_id = t.id
             WHERE p.deleted_at IS NULL";

// Initialize parameters array and types string
$params = [];
$types = "";

// Build search conditions
if ($active_filter !== '') {
    $base_sql .= " AND p.is_active = ?";
    $params[] = $active_filter;
    $types .= "i";
}

if (!empty($search)) {
    switch ($search_by) {
        case 'title':
            $base_sql .= " AND p.title LIKE ?";
            break;
        case 'author':
            $base_sql .= " AND u.username LIKE ?";
            break;
        case 'category':
            $base_sql .= " AND c.name LIKE ?";
            break;
        case 'tag':
            $base_sql .= " AND t.name LIKE ?";
            break;
    }
    $params[] = "%$search%";
    $types .= "s";
}

// Add date conditions to SQL
if (!empty($date_from) || !empty($date_to)) {
if (!empty($date_from) && !empty($date_to)) {
        // Both dates provided
        $base_sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    } elseif (!empty($date_from)) {
        // Only from date provided
        $base_sql .= " AND DATE(p.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    } elseif (!empty($date_to)) {
        // Only to date provided
        $base_sql .= " AND DATE(p.created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
}

if (!empty($status_filter)) {
    $base_sql .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get total number of posts (for pagination)
$count_sql = "SELECT COUNT(DISTINCT p.id) as total " . $base_sql;
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$total_posts = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = max(1, ceil($total_posts / $posts_per_page));
$count_stmt->close();

// Ensure current page is within valid range
$page = min($page, $total_pages);

// Get posts with all details
$sql = "SELECT DISTINCT p.*, 
        c.name as category_name, 
        u.username as author_name,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags 
        " . $base_sql . "
        GROUP BY p.id 
        ORDER BY p.created_at " . strtoupper($sort_order) . " 
        LIMIT ? OFFSET ?";

// Add pagination parameters
$params[] = $posts_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Optimize statistics queries by combining them
$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL) as total_posts,
    (SELECT COUNT(*) FROM posts WHERE status = 'published' AND is_active = 1 AND deleted_at IS NULL) as published_posts,
    (SELECT COUNT(*) FROM posts WHERE status = 'draft' AND deleted_at IS NULL) as draft_posts,
    (SELECT COUNT(*) FROM posts WHERE featured = 1 AND is_active = 1 AND deleted_at IS NULL) as featured_posts,
    (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_active = 1) as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin' AND deleted_at IS NULL AND is_active = 1) as admin_users,
    (SELECT COUNT(*) FROM users WHERE role = 'author' AND deleted_at IS NULL AND is_active = 1) as author_users,
    (SELECT COUNT(*) FROM users WHERE role = 'user' AND deleted_at IS NULL AND is_active = 1) as normal_users,
    (SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL AND is_active = 1) as total_categories,
    (SELECT COUNT(*) FROM tags WHERE deleted_at IS NULL AND is_active = 1) as total_tags,
    (SELECT COUNT(*) FROM netpy_newsletter_users WHERE deleted_at IS NULL AND is_active = 1) as total_subscribers";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get recent posts
$recent_posts_stmt = $conn->prepare("SELECT p.*, c.name as category_name, u.username as author_name,
                     GROUP_CONCAT(DISTINCT t.name ORDER BY t.name ASC SEPARATOR ', ') as post_tags 
                     FROM posts p 
                     LEFT JOIN categories c ON p.category_id = c.id 
                     LEFT JOIN users u ON p.author_id = u.id 
                     LEFT JOIN post_tags pt ON p.id = pt.post_id
                     LEFT JOIN tags t ON pt.tag_id = t.id
                     WHERE p.deleted_at IS NULL 
                     AND c.deleted_at IS NULL 
                     AND c.is_active = 1
                     AND u.deleted_at IS NULL 
                     AND u.is_active = 1
                     GROUP BY p.id, c.name, u.username
                     ORDER BY p.created_at DESC 
                     LIMIT 10");
$recent_posts_stmt->execute();
$recent_posts = $recent_posts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_posts_stmt->close();

// Get recent comments
$recent_comments_stmt = $conn->prepare("SELECT c.*, p.title as post_title, u.username 
                       FROM comments c 
                       LEFT JOIN posts p ON c.post_id = p.id 
                       LEFT JOIN users u ON c.user_id = u.id 
                       WHERE c.deleted_at IS NULL 
                       AND c.is_active = 1
                       AND p.deleted_at IS NULL 
                       AND p.is_active = 1
                       AND u.deleted_at IS NULL 
                       AND u.is_active = 1
                       ORDER BY c.created_at DESC 
                       LIMIT 5");
$recent_comments_stmt->execute();
$recent_comments = $recent_comments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_comments_stmt->close();

// Get recent subscribers
$recent_subscribers_stmt = $conn->prepare("SELECT * FROM netpy_newsletter_users 
                                         WHERE deleted_at IS NULL 
                                         AND is_active = 1 
                                         ORDER BY subscribed_at DESC 
                                         LIMIT 5");
$recent_subscribers_stmt->execute();
$recent_subscribers = $recent_subscribers_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recent_subscribers_stmt->close();

$page_title = "Dashboard";
include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Dashboard - NetPy Blog</title>
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
        .input-group.has-filter .form-control {
            border-color: #17a2b8;
        }
        .active-filters {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .active-filters .badge {
            font-size: 90%;
            padding: 5px 10px;
        }
        .clear-filter {
            height: 38px;
            line-height: 24px;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .d-flex .input-group {
            flex: 1;
        }
        .input-group-append .btn {
            z-index: 0;
        }
        .filter-group {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .filter-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-bottom: 0.5rem;
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
                        <a href="manage-newsletter.php" class="btn btn-warning">Manage Subscribers</a>
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
                                <p class="h4"><?php echo $stats['total_users']; ?></p>
                                <small>
                                    Admins: <?php echo $stats['admin_users']; ?> |
                                    Authors: <?php echo $stats['author_users']; ?> |
                                    Users: <?php echo $stats['normal_users']; ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <h3>Newsletter</h3>
                                <p>Total Subscribers: <?php echo $stats['total_subscribers']; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Newsletter Subscribers Section -->
                    <div class="stats-box mt-4">
                        <h3>Recent Newsletter Subscribers</h3>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Subscribed Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_subscribers as $subscriber): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($subscriber['subscribed_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Posts -->
                    <div class="all-blog-posts">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="sidebar-item recent-posts">
                                    <div class="sidebar-heading">
                                        <h2>Posts</h2>
                                    </div>
                                    <!-- Search Form -->
                                    <div class="mb-4">
                                        <form method="GET" class="form" id="searchForm">
                                            <div class="row">
                                                <div class="col-md-3 mb-2">
                                                    <label for="search">Search</label>
                                                    <div class="d-flex">
                                                        <div class="input-group">
                                                            <input type="text" id="search" name="search" class="form-control" placeholder="Enter search term..." value="<?php echo htmlspecialchars($search); ?>">
                                                        </div>
                                                        <?php if (!empty($search)): ?>
                                                            <button type="button" class="btn btn-outline-secondary ml-2 clear-filter" data-clear="search">
                                                                Clear
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 mb-2">
                                                    <label for="search_by">Search By</label>
                                                    <div class="d-flex">
                                                        <div class="input-group">
                                                            <select name="search_by" id="search_by" class="form-control">
                                                                <option value="title" <?php echo $search_by === 'title' ? 'selected' : ''; ?>>Title</option>
                                                                <option value="author" <?php echo $search_by === 'author' ? 'selected' : ''; ?>>Author</option>
                                                                <option value="category" <?php echo $search_by === 'category' ? 'selected' : ''; ?>>Category</option>
                                                                <option value="tag" <?php echo $search_by === 'tag' ? 'selected' : ''; ?>>Tag</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 mb-2">
                                                    <label for="status">Status</label>
                                                    <div class="d-flex">
                                                        <div class="input-group">
                                                            <select name="status" id="status" class="form-control">
                                                                <option value="">All Status</option>
                                                                <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                                                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 mb-2">
                                                    <label for="active">Active Status</label>
                                                    <div class="d-flex">
                                                        <div class="input-group">
                                                            <select name="active" id="active" class="form-control">
                                                                <option value="">All Posts</option>
                                                                <option value="1" <?php echo $active_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="0" <?php echo $active_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-2 mb-2">
                                                    <label for="sort_order">Sort Order</label>
                                                    <div class="d-flex">
                                                        <div class="input-group">
                                                            <select name="sort_order" id="sort_order" class="form-control">
                                                                <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                                                                <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-2">
                                                    <label>&nbsp;</label>
                                                    <button type="submit" class="btn btn-primary w-100">
                                                        <i class="fa fa-search"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="row mt-2">
                                                <div class="col-md-3 mb-2">
                                                    <label for="date_from">From Date</label>
                                                    <div class="d-flex">
                                                        <div class="input-group">
                                                            <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>" max="<?php echo date('Y-m-d'); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <label for="date_to">To Date</label>
                                                    <div class="d-flex">
                                                        <div class="input-group">
                                                            <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>" max="<?php echo date('Y-m-d'); ?>">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label>&nbsp;</label>
                                                    <div>
                                                        <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($active_filter) || $sort_order !== 'desc'): ?>
                                                            <a href="dashboard.php" class="btn btn-secondary">
                                                                <i class="fa fa-times"></i> Clear All Filters
                                                            </a>
                                                        <?php endif; ?>
                                                        <span class="ml-2">
                                                            Found <?php echo $total_posts; ?> post<?php echo $total_posts !== 1 ? 's' : ''; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($active_filter) || $sort_order !== 'desc'): ?>
                                            <div class="row mt-2">
                                                <div class="col-12">
                                                    <div class="active-filters">
                                                        <small class="text-muted">Active Filters:</small>
                                                        <?php if (!empty($search)): ?>
                                                            <span class="badge badge-info mr-2">Search: <?php echo htmlspecialchars($search); ?> (<?php echo ucfirst($search_by); ?>)</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($status_filter)): ?>
                                                            <span class="badge badge-info mr-2">Status: <?php echo ucfirst($status_filter); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($active_filter)): ?>
                                                            <span class="badge badge-info mr-2">Active Status: <?php echo $active_filter === '1' ? 'Active' : 'Inactive'; ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($date_from) || !empty($date_to)): ?>
                                                            <span class="badge badge-info mr-2">Date Range: <?php 
                                                                if (!empty($date_from) && !empty($date_to)) {
                                                                    echo date('M j, Y', strtotime($date_from)) . ' to ' . date('M j, Y', strtotime($date_to));
                                                                } elseif (!empty($date_from)) {
                                                                    echo 'From ' . date('M j, Y', strtotime($date_from));
                                                                } else {
                                                                    echo 'Until ' . date('M j, Y', strtotime($date_to));
                                                                }
                                                            ?></span>
                                                        <?php endif; ?>
                                                        <span class="badge badge-info mr-2">Sort: <?php echo $sort_order === 'desc' ? 'Newest First' : 'Oldest First'; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                    <div class="content">
                                        <?php if (empty($posts)): ?>
                                            <div class="alert alert-info">
                                                No posts found matching your criteria.
                                            </div>
                                        <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="thead-light">
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
                                                <?php foreach ($posts as $post): ?>
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
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-primary">
                                                                    <i class="fa fa-edit"></i> Edit
                                                                </a>
                                                                <form method="POST" action="delete-post.php" style="display: inline;">
                                                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                                    <input type="hidden" name="new_status" value="<?php echo $post['is_active'] ? '0' : '1'; ?>">
                                                                    <button type="submit" class="btn btn-<?php echo $post['is_active'] ? 'warning' : 'success'; ?>">
                                                                        <?php echo $post['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        </div>
                                        <?php endif; ?>
                                        
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
                                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                                echo '&sort_order='.urlencode($sort_order);
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
                                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                                echo '&sort_order='.urlencode($sort_order);
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
                                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                                echo '&sort_order='.urlencode($sort_order);
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
                                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                                echo '&sort_order='.urlencode($sort_order);
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
                                                                echo !empty($date_from) ? '&date_from='.urlencode($date_from) : '';
                                                                echo !empty($date_to) ? '&date_to='.urlencode($date_to) : '';
                                                                echo '&sort_order='.urlencode($sort_order);
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
    
    <script>
    $(document).ready(function() {
        // Set max date for date inputs to today
        var today = new Date().toISOString().split('T')[0];
        $('#date_from, #date_to').attr('max', today);
        
        // Handle date input changes
        $('#date_from').on('change', function() {
            var fromDate = $(this).val();
            var toDate = $('#date_to').val();
            
            if (fromDate && toDate && fromDate > toDate) {
                $('#date_to').val(fromDate);
            }
            
            $(this).closest('form').submit();
        });
        
        $('#date_to').on('change', function() {
            var fromDate = $('#date_from').val();
            var toDate = $(this).val();
            
            if (fromDate && toDate && fromDate > toDate) {
                $('#date_from').val(toDate);
            }
            
            $(this).closest('form').submit();
        });
        
        // Auto-submit form when certain filters change
        $('#active').on('change', function() {
            $(this).closest('form').submit();
        });
        
        // Handle clear filter buttons
        $('.clear-filter').on('click', function(e) {
            e.preventDefault();
            var filterToClear = $(this).data('clear');
            var $form = $('#searchForm');
            
            // Special handling for related filters
            switch(filterToClear) {
                case 'search':
                    // Clear both search and search_by
                    $form.find('[name="search"]').val('');
                    $form.find('[name="search_by"]').val('title');
                    break;
                case 'search_by':
                    // Reset search_by to default (title)
                    $form.find('[name="search_by"]').val('title');
                    break;
                case 'date_from':
                    // Clear from date
                    $form.find('[name="date_from"]').val('');
                    break;
                case 'date_to':
                    // Clear to date
                    $form.find('[name="date_to"]').val('');
                    break;
                default:
                    // Clear the specific filter
                    $form.find('[name="' + filterToClear + '"]').val('');
            }
            
            // Submit the form
            $form.submit();
        });
        
        // Handle post status toggle
        $('button[name="toggle_status"]').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $form = $button.closest('form');
            var action = $button.text().trim().toLowerCase();
            var title = $button.closest('tr').find('td:first').text().trim();
            
            if (confirm('Are you sure you want to ' + action + ' the post "' + title + '"?')) {
                // Show loading state
                $button.prop('disabled', true);
                $button.html('<i class="fa fa-spinner fa-spin"></i> Processing...');
                
                // Submit the form using AJAX
                $.ajax({
                    url: $form.attr('action'),
                    type: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        // Reload the page to show updated status
                        window.location.reload();
                    },
                    error: function() {
                        alert('Error updating post status. Please try again.');
                        $button.prop('disabled', false);
                        $button.html('<i class="fa fa-' + ($button.hasClass('btn-warning') ? 'times' : 'check') + '"></i> ' + 
                                   ($button.hasClass('btn-warning') ? 'Deactivate' : 'Activate'));
                    }
                });
            }
        });
        
        // Add confirmation for activate/deactivate
        $('form[action="delete-post.php"]').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var action = $form.find('button').text().trim().toLowerCase();
            var title = $form.closest('tr').find('td:first').text().trim();
            
            if (confirm('Are you sure you want to ' + action + ' the post "' + title + '"?')) {
                $form.off('submit').submit();
            }
        });
        
        // Show success/error messages if they exist
        <?php if (isset($_SESSION['success_msg'])): ?>
            alert('<?php echo addslashes($_SESSION['success_msg']); ?>');
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_msg'])): ?>
            alert('<?php echo addslashes($_SESSION['error_msg']); ?>');
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>
    });
    </script>
</body>
</html> 