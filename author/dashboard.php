<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit();
}

// Get search parameters
$search = isset($_GET['search']) ? trim(htmlspecialchars($_GET['search'])) : '';
$search_by = isset($_GET['search_by']) && in_array($_GET['search_by'], ['title', 'category', 'tag']) ? $_GET['search_by'] : 'title';
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['published', 'draft']) ? $_GET['status'] : '';
$active_filter = isset($_GET['active']) && in_array($_GET['active'], ['0', '1']) ? $_GET['active'] : '';
$show_deleted = isset($_GET['show_deleted']) ? $_GET['show_deleted'] : '0';
$sort_order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['asc', 'desc']) ? $_GET['sort_order'] : 'desc';

// Validate and format date inputs
$date_from = isset($_GET['date_from']) && strtotime($_GET['date_from']) ? date('Y-m-d', strtotime($_GET['date_from'])) : '';
$date_to = isset($_GET['date_to']) && strtotime($_GET['date_to']) ? date('Y-m-d', strtotime($_GET['date_to'])) : '';

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

// Initialize parameters array and types string for count query
$count_params = array($_SESSION['user_id']);
$count_types = "i";

// Add active/inactive filter
if ($active_filter !== '') {
    $count_sql .= " AND p.is_active = ?";
    $count_params[] = $active_filter;
    $count_types .= "i";
}

// Add search conditions to count query
if (!empty($search)) {
    switch ($search_by) {
        case 'title':
            $count_sql .= " AND p.title LIKE ?";
            $count_params[] = "%$search%";
            $count_types .= "s";
            break;
        case 'category':
            $count_sql .= " AND c.name LIKE ?";
            $count_params[] = "%$search%";
            $count_types .= "s";
            break;
        case 'tag':
            $count_sql .= " AND t.name LIKE ?";
            $count_params[] = "%$search%";
            $count_types .= "s";
            break;
    }
}

// Add date conditions to SQL
if (!empty($date_from) || !empty($date_to)) {
    if (!empty($date_from) && !empty($date_to)) {
        // Both dates provided
        $count_sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
        $count_params[] = $date_from;
        $count_params[] = $date_to;
        $count_types .= "ss";
    } elseif (!empty($date_from)) {
        // Only from date provided
        $count_sql .= " AND DATE(p.created_at) >= ?";
        $count_params[] = $date_from;
        $count_types .= "s";
    } elseif (!empty($date_to)) {
        // Only to date provided
        $count_sql .= " AND DATE(p.created_at) <= ?";
        $count_params[] = $date_to;
        $count_types .= "s";
    }
}

// Add status filter
if (!empty($status_filter)) {
    $count_sql .= " AND p.status = ?";
    $count_params[] = $status_filter;
    $count_types .= "s";
}

$count_stmt = $conn->prepare($count_sql);

// Bind parameters dynamically for count query
if (!empty($count_params)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
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

// Initialize parameters array and types string for main query
$params = array($_SESSION['user_id']);
$types = "i";

// Add active/inactive filter
if ($active_filter !== '') {
    $sql .= " AND p.is_active = ?";
    $params[] = $active_filter;
    $types .= "i";
}

// Add search conditions
if (!empty($search)) {
    switch ($search_by) {
        case 'title':
            $sql .= " AND p.title LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
            break;
        case 'category':
            $sql .= " AND c.name LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
            break;
        case 'tag':
            $sql .= " AND t.name LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
            break;
    }
}

// Add date conditions to main query
if (!empty($date_from) || !empty($date_to)) {
    if (!empty($date_from) && !empty($date_to)) {
        $sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    } elseif (!empty($date_from)) {
        $sql .= " AND DATE(p.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    } elseif (!empty($date_to)) {
        $sql .= " AND DATE(p.created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
}

// Add status filter
if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " GROUP BY p.id ORDER BY p.created_at " . strtoupper($sort_order) . " LIMIT ? OFFSET ?";

// Add LIMIT and OFFSET parameters
$params[] = $posts_per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($sql);

// Bind parameters dynamically for main query
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">

    <style>
        .blog-posts {
            margin-top: 30px;
            padding: 30px 0;
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            box-shadow: 0 0 20px rgba(0,0,0,0.08);
            border-radius: 15px;
            margin-bottom: 30px;
            background-color: #fff;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #eee;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
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
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            text-align: center;
            transition: transform 0.3s ease;
            border: none;
        }
        .stats-box:hover {
            transform: translateY(-5px);
        }
        .stats-box i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: #0047cc;
        }
        .stats-box h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #20232e;
            margin-bottom: 10px;
        }
        .stats-box p {
            color: #666;
            margin: 0;
            font-size: 1rem;
        }
        .table {
            margin: 0;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            padding: 15px;
            color: #20232e;
        }
        .table td {
            padding: 15px;
            vertical-align: middle;
        }
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
            border-radius: 0.5rem;
        }
        .search-form {
            margin-bottom: 30px;
        }
        .search-form .form-control,
        .search-form .form-select {
            border-radius: 10px;
            padding: 12px 20px;
            border: 1px solid #e1e1e1;
            height: auto;
        }
        .search-form input[type="date"] {
            padding: 10px 20px;
        }
        .search-form .btn {
            padding: 12px 25px;
            border-radius: 10px;
        }
        .search-form .date-inputs {
            display: flex;
            gap: 10px;
        }
        .search-form input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.6;
            filter: invert(0.8);
        }
        .search-form input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
        }
        .active-filters {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 15px;
        }
        .active-filters .badge {
            font-size: 90%;
            padding: 6px 12px;
            background-color: #0047cc !important;
        }
        .clear-filter {
            height: 32px;
            line-height: 20px;
            font-size: 0.875rem;
            padding: 5px 12px;
            white-space: nowrap;
        }
        .input-group.has-filter .form-control {
            border-color: #0047cc;
        }
        .pagination {
            margin-top: 30px;
            justify-content: center;
        }
        .pagination .page-link {
            padding: 12px 20px;
            margin: 0 5px;
            border-radius: 10px;
            border: none;
            color: #20232e;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        .pagination .page-link:hover,
        .pagination .page-item.active .page-link {
            background-color: #0047cc;
            color: #fff;
        }
        .status-badge {
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-published {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .status-draft {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        .toggle-status {
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }
        .toggle-status.active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .toggle-status.inactive {
            background-color: #fbe9e7;
            color: #d32f2f;
        }
        .toggle-status:hover {
            opacity: 0.9;
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
                        <div class="col-md-3">
                            <div class="stats-box">
                                <i class="fas fa-newspaper"></i>
                                <h3><?php echo $stats['total_posts']; ?></h3>
                                <p>Total Posts</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <i class="fas fa-check-circle"></i>
                                <h3><?php echo $stats['published_posts']; ?></h3>
                                <p>Published Posts</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <i class="fas fa-edit"></i>
                                <h3><?php echo $stats['draft_posts']; ?></h3>
                                <p>Draft Posts</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-box">
                                <i class="fas fa-star"></i>
                                <h3><?php echo $stats['featured_posts']; ?></h3>
                                <p>Featured Posts</p>
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
                            <form method="GET" class="search-form">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <select name="search_by" class="form-select">
                                            <option value="title" <?php echo $search_by === 'title' ? 'selected' : ''; ?>>Title</option>
                                            <option value="category" <?php echo $search_by === 'category' ? 'selected' : ''; ?>>Category</option>
                                            <option value="tag" <?php echo $search_by === 'tag' ? 'selected' : ''; ?>>Tag</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="status" class="form-select">
                                            <option value="">All Status</option>
                                            <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                                            <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="active" class="form-select">
                                            <option value="">All Posts</option>
                                            <option value="1" <?php echo $active_filter === '1' ? 'selected' : ''; ?>>Active</option>
                                            <option value="0" <?php echo $active_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select name="sort_order" class="form-select">
                                            <option value="desc" <?php echo $sort_order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                                            <option value="asc" <?php echo $sort_order === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="date-inputs d-flex gap-2">
                                            <div class="flex-grow-1">
                                                <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>" title="From Date">
                                            </div>
                                            <div class="flex-grow-1">
                                                <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>" title="To Date">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($search) || !empty($status_filter) || !empty($active_filter) || !empty($date_from) || !empty($date_to)): ?>
                                <div class="active-filters mt-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted">Active Filters:</span>
                                        <?php if (!empty($search)): ?>
                                            <span class="badge bg-info">Search: <?php echo htmlspecialchars($search); ?> (<?php echo ucfirst($search_by); ?>)</span>
                                        <?php endif; ?>
                                        <?php if (!empty($status_filter)): ?>
                                            <span class="badge bg-info">Status: <?php echo ucfirst($status_filter); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($active_filter)): ?>
                                            <span class="badge bg-info">Active: <?php echo $active_filter === '1' ? 'Yes' : 'No'; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($date_from) || !empty($date_to)): ?>
                                            <span class="badge bg-info">
                                                Date: 
                                                <?php 
                                                if (!empty($date_from) && !empty($date_to)) {
                                                    echo date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to));
                                                } elseif (!empty($date_from)) {
                                                    echo 'From ' . date('M d, Y', strtotime($date_from));
                                                } else {
                                                    echo 'Until ' . date('M d, Y', strtotime($date_to));
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge bg-info">Sort: <?php echo $sort_order === 'desc' ? 'Newest First' : 'Oldest First'; ?></span>
                                        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary clear-filter">
                                            <i class="fas fa-times"></i> Clear All
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </form>

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Tags</th>
                                            <th>Status</th>
                                            <th>Active</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($posts)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No posts found</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($posts as $post): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($post['featured']): ?>
                                                            <i class="fas fa-star text-warning me-2" title="Featured Post"></i>
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($post['title']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($post['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($post['post_tags']); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $post['status'] === 'published' ? 'status-published' : 'status-draft'; ?>">
                                                        <?php echo ucfirst($post['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" action="delete-post.php" style="display: inline;" class="toggle-form">
                                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <button type="submit" class="toggle-status <?php echo $post['is_active'] ? 'active' : 'inactive'; ?>">
                                                            <?php echo $post['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </button>
                                                    </form>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" action="delete-post.php" style="display: inline;" class="delete-form">
                                                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                <div class="pagination justify-content-center mt-4">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&search_by=<?php echo urlencode($search_by); ?>&status=<?php echo urlencode($status_filter); ?>&active=<?php echo urlencode($active_filter); ?>&sort_order=<?php echo urlencode($sort_order); ?>">
                                                        <i class="fas fa-chevron-left"></i>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&search_by=<?php echo urlencode($search_by); ?>&status=<?php echo urlencode($status_filter); ?>&active=<?php echo urlencode($active_filter); ?>&sort_order=<?php echo urlencode($sort_order); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&search_by=<?php echo urlencode($search_by); ?>&status=<?php echo urlencode($status_filter); ?>&active=<?php echo urlencode($active_filter); ?>&sort_order=<?php echo urlencode($sort_order); ?>">
                                                        <i class="fas fa-chevron-right"></i>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Handle post status toggle
        $('.toggle-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const button = form.find('button');
            const currentStatus = button.hasClass('active');
            const postTitle = form.closest('tr').find('td:first').text().trim();
            
            if (confirm(`Are you sure you want to ${currentStatus ? 'deactivate' : 'activate'} the post "${postTitle}"?`)) {
                button.html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        button.toggleClass('active inactive')
                              .html(currentStatus ? 'Inactive' : 'Active');
                    },
                    error: function() {
                        alert('An error occurred while updating the post status.');
                        button.html(currentStatus ? 'Active' : 'Inactive');
                    }
                });
            }
        });

        // Handle post deletion
        $('.delete-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const postTitle = form.closest('tr').find('td:first').text().trim();
            
            if (confirm(`Are you sure you want to delete the post "${postTitle}"? This action cannot be undone.`)) {
                form.find('button').html('<i class="fas fa-spinner fa-spin"></i>');
                $.ajax({
                    url: form.attr('action'),
                    method: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        form.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                            if ($('tbody tr').length === 0) {
                                $('tbody').html('<tr><td colspan="7" class="text-center py-4">No posts found</td></tr>');
                            }
                        });
                    },
                    error: function() {
                        alert('An error occurred while deleting the post.');
                        form.find('button').html('<i class="fas fa-trash"></i>');
                    }
                });
            }
        });
    });
    </script>
</body>
</html> 