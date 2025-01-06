<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle tag operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' && !empty($_POST['name'])) {
            $name = html_entity_decode(sanitizeInput($_POST['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = createSlug($name);
            
            $sql = "INSERT INTO tags (name, slug) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $slug);
            $stmt->execute();
        } 
        elseif ($_POST['action'] === 'delete' && !empty($_POST['tag_id'])) {
            $tag_id = (int)$_POST['tag_id'];
            
            // Soft delete the tag
            $sql = "UPDATE tags SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $tag_id);
            $stmt->execute();
        }
        elseif ($_POST['action'] === 'toggle_status' && !empty($_POST['tag_id'])) {
            $tag_id = (int)$_POST['tag_id'];
            $new_status = (int)$_POST['new_status'];
            
            $sql = "UPDATE tags SET is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_status, $tag_id);
            $stmt->execute();
        }
    }
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$show_deleted = isset($_GET['show_deleted']) ? $_GET['show_deleted'] : '0';

// Initialize parameters array and types string
$params = array();
$types = '';

// Base query
$sql = "SELECT t.*, COUNT(DISTINCT pt.post_id) as post_count 
        FROM tags t 
        LEFT JOIN post_tags pt ON t.id = pt.tag_id 
        LEFT JOIN posts p ON pt.post_id = p.id AND p.deleted_at IS NULL 
        WHERE " . ($show_deleted === '1' ? "t.deleted_at IS NOT NULL" : "t.deleted_at IS NULL");

// Add search condition if search term is provided
if (!empty($search)) {
    $sql .= " AND t.name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$sql .= " GROUP BY t.id ORDER BY t.name ASC";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$tags = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Manage Tags - NetPy Blog</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">
    <style>
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: white;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #212529;
        }
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: white;
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
                            <h2>Manage Tags</h2>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <section class="contact-us">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="down-contact">
                        <div class="sidebar-item contact-form">
                            <div class="sidebar-heading">
                                <h2>Add New Tag</h2>
                            </div>
                            <div class="content">
                                <form method="post">
                                    <div class="row">
                                        <div class="col-md-6 col-sm-12">
                                            <fieldset>
                                                <input type="text" name="name" placeholder="Tag Name" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" name="action" value="add" class="btn btn-primary">Add Tag</button>
                                            </fieldset>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-12 mt-4">
                    <div class="down-contact">
                        <div class="sidebar-item contact-form">
                            <div class="sidebar-heading">
                                <h2>Existing Tags</h2>
                            </div>
                            <div class="content">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <form method="GET" action="manage-tags.php">
                                            <div class="input-group">
                                                <input type="text" name="search" class="form-control" placeholder="Search tags..." value="<?php echo htmlspecialchars($search); ?>">
                                                <select name="show_deleted" class="form-control">
                                                    <option value="0" <?php echo $show_deleted === '0' ? 'selected' : ''; ?>>Active Tags</option>
                                                    <option value="1" <?php echo $show_deleted === '1' ? 'selected' : ''; ?>>Deleted Tags</option>
                                                </select>
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-primary">Search</button>
                                                    <?php if (!empty($search) || $show_deleted !== '0'): ?>
                                                        <a href="manage-tags.php" class="btn btn-secondary">Clear</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Slug</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tags as $tag): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($tag['name']); ?></td>
                                                <td><?php echo htmlspecialchars($tag['slug']); ?></td>
                                                <td>
                                                    <?php if ($show_deleted === '0'): ?>
                                                        <form action="manage-tags.php" method="post" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                                            <input type="hidden" name="new_status" value="<?php echo $tag['is_active'] ? '0' : '1'; ?>">
                                                            <button type="submit" class="btn <?php echo $tag['is_active'] ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                                <?php echo $tag['is_active'] ? '<i class="fa fa-pause"></i> Deactivate' : '<i class="fa fa-play"></i> Activate'; ?>
                                                            </button>
                                                        </form>
                                                        <form action="manage-tags.php" method="post" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this tag?')">
                                                                <i class="fa fa-trash"></i> Delete
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