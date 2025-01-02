<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Handle category creation/deletion
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
            // Check if category has posts
            $category_id = (int)$_POST['category_id'];
            $sql = "SELECT COUNT(*) as post_count FROM posts WHERE category_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['post_count'] > 0) {
                $error = "Cannot delete category. It has associated posts.";
            } else {
                $sql = "DELETE FROM categories WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $category_id);
                $stmt->execute();
            }
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

// Get all categories
$sql = "SELECT c.*, COUNT(p.id) as post_count 
        FROM categories c 
        LEFT JOIN posts p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY c.name";
$result = $conn->query($sql);
$categories = $result->fetch_all(MYSQLI_ASSOC);
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
                                                    <button class="btn btn-primary btn-sm edit-category">Edit</button>
                                                    <form action="manage-categories.php" method="post" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this category?')" <?php echo $category['post_count'] > 0 ? 'disabled' : ''; ?>>Delete</button>
                                                    </form>
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