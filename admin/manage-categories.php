<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' && !empty($_POST['name'])) {
            $name = html_entity_decode(sanitizeInput($_POST['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = createSlug($name);
            
            $sql = "INSERT INTO categories (name, slug) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $name, $slug);
            $stmt->execute();
        } 
        elseif ($_POST['action'] === 'delete' && !empty($_POST['category_id'])) {
            $category_id = (int)$_POST['category_id'];
            // Soft delete the category
            $sql = "UPDATE categories SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
        }
        elseif ($_POST['action'] === 'toggle_status' && !empty($_POST['category_id'])) {
            $category_id = (int)$_POST['category_id'];
            $new_status = (int)$_POST['new_status'];
            
            $sql = "UPDATE categories SET is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $new_status, $category_id);
            $stmt->execute();
        }
        elseif ($_POST['action'] === 'edit' && !empty($_POST['category_id']) && !empty($_POST['name'])) {
            $category_id = (int)$_POST['category_id'];
            $name = html_entity_decode(sanitizeInput($_POST['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = createSlug($name);
            
            $sql = "UPDATE categories SET name = ?, slug = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $slug, $category_id);
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
$sql = "SELECT c.*, COUNT(p.id) as post_count 
        FROM categories c 
        LEFT JOIN posts p ON c.id = p.category_id AND p.deleted_at IS NULL 
        WHERE " . ($show_deleted === '1' ? "c.deleted_at IS NOT NULL" : "c.deleted_at IS NULL");

// Add search condition if search term is provided
if (!empty($search)) {
    $sql .= " AND c.name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$sql .= " GROUP BY c.id ORDER BY c.name ASC";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$categories = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Manage Categories - NetPy Blog</title>
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
                            <h2>Manage Categories</h2>
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
                                <h2>Add New Category</h2>
                            </div>
                            <div class="content">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $error; ?>
                                    </div>
                                <?php endif; ?>
                                <form action="manage-categories.php" method="post">
                                    <input type="hidden" name="action" value="add">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <fieldset>
                                                <input name="name" type="text" placeholder="Category Name" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" class="main-button">Add Category</button>
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
                                <h2>Existing Categories</h2>
                            </div>
                            <div class="content">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <form method="GET" action="manage-categories.php">
                                            <div class="input-group">
                                                <input type="text" name="search" class="form-control" placeholder="Search categories..." value="<?php echo htmlspecialchars($search); ?>">
                                                <select name="show_deleted" class="form-control">
                                                    <option value="0" <?php echo $show_deleted === '0' ? 'selected' : ''; ?>>Active Categories</option>
                                                    <option value="1" <?php echo $show_deleted === '1' ? 'selected' : ''; ?>>Deleted Categories</option>
                                                </select>
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-primary">Search</button>
                                                    <?php if (!empty($search) || $show_deleted !== '0'): ?>
                                                        <a href="manage-categories.php" class="btn btn-secondary">Clear</a>
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
                                                <th>Posts</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td>
                                                    <span class="category-name" data-id="<?php echo $category['id']; ?>">
                                                        <?php echo $category['name']; ?>
                                                    </span>
                                                    <form action="manage-categories.php" method="post" class="edit-form" style="display: none;">
                                                        <input type="hidden" name="action" value="edit">
                                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                        <div class="input-group">
                                                            <input type="text" name="name" value="<?php echo $category['name']; ?>" class="form-control">
                                                            <div class="input-group-append">
                                                                <button type="submit" class="btn btn-success btn-sm">Save</button>
                                                                <button type="button" class="btn btn-secondary btn-sm cancel-edit">Cancel</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </td>
                                                <td><?php echo htmlspecialchars($category['slug']); ?></td>
                                                <td><?php echo $category['post_count']; ?></td>
                                                <td>
                                                    <?php if ($show_deleted === '0'): ?>
                                                        <button class="btn btn-primary btn-sm edit-category">
                                                            <i class="fa fa-edit"></i> Edit
                                                        </button>
                                                        <form action="manage-categories.php" method="post" style="display: inline;">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                            <input type="hidden" name="new_status" value="<?php echo $category['is_active'] ? '0' : '1'; ?>">
                                                            <button type="submit" class="btn <?php echo $category['is_active'] ? 'btn-warning' : 'btn-success'; ?> btn-sm">
                                                                <?php echo $category['is_active'] ? '<i class="fa fa-pause"></i> Deactivate' : '<i class="fa fa-play"></i> Activate'; ?>
                                                            </button>
                                                        </form>
                                                        <form action="manage-categories.php" method="post" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this category?')">
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
    <script>
        $(document).ready(function() {
            // Edit category
            $('.edit-category').click(function() {
                var row = $(this).closest('tr');
                row.find('.category-name').hide();
                row.find('.edit-form').show();
                $(this).hide();
            });

            // Cancel edit
            $('.cancel-edit').click(function() {
                var row = $(this).closest('tr');
                row.find('.edit-form').hide();
                row.find('.category-name').show();
                row.find('.edit-category').show();
            });
        });
    </script>
</body>
</html> 