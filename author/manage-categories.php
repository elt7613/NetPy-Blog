<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $name = trim($_POST['name']);
            if (!empty($name)) {
                $slug = createSlug($name);
                $stmt = $conn->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $slug);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Category added successfully!";
                } else {
                    $error = "Error adding category.";
                }
                $stmt->close();
            }
            break;
            
        case 'edit':
            $category_id = $_POST['category_id'];
            $name = trim($_POST['name']);
            if (!empty($name) && !empty($category_id)) {
                $slug = createSlug($name);
                $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $slug, $category_id);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Category updated successfully!";
                } else {
                    $error = "Error updating category.";
                }
                $stmt->close();
            }
            break;
            
        case 'deactivate':
            $category_id = $_POST['category_id'];
            if (!empty($category_id)) {
                $stmt = $conn->prepare("UPDATE categories SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $category_id);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Category deactivated successfully!";
                } else {
                    $error = "Error deactivating category.";
                }
                $stmt->close();
            }
            break;
    }
    
    // Redirect to refresh the page
    header('Location: manage-categories.php');
    exit;
}

// Get all active categories with post count
$sql = "SELECT c.*, COUNT(p.id) as post_count 
        FROM categories c 
        LEFT JOIN posts p ON c.id = p.category_id AND p.deleted_at IS NULL
        WHERE c.is_active = 1 AND c.deleted_at IS NULL
        GROUP BY c.id 
        ORDER BY c.name";
$result = $conn->query($sql);
$categories = $result->fetch_all(MYSQLI_ASSOC);

$page_title = "Manage Categories";
include '../includes/header.php';
?>

<!-- Page Content -->
<div class="heading-page header-text">
    <section class="page-heading">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-content">
                        <h4>Author</h4>
                        <h2>Manage Categories</h2>
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
                <div class="card">
                    <div class="card-header">
                        <h4>Add New Category</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success_msg'])): ?>
                            <div class="alert alert-success">
                                <?php 
                                echo $_SESSION['success_msg'];
                                unset($_SESSION['success_msg']);
                                ?>
                            </div>
                        <?php endif; ?>

                        <form action="manage-categories.php" method="post">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <input name="name" type="text" class="form-control" placeholder="Category Name" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Category</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h4>Existing Categories</h4>
                    </div>
                    <div class="card-body">
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
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to deactivate this category?');">
                                                    Deactivate
                                                </button>
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
</section>

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

<?php include '../includes/footer.php'; ?> 