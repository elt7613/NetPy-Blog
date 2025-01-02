<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit;
}

// Handle category creation/deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' && !empty($_POST['name'])) {
            $name = html_entity_decode(sanitizeInput($_POST['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = createSlug($name);
            
            // Check if category already exists
            $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
            $stmt->bind_param("ss", $name, $slug);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Category already exists.";
            } else {
                $sql = "INSERT INTO categories (name, slug) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $name, $slug);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Category created successfully!";
                    header('Location: manage-categories.php');
                    exit();
                } else {
                    $error = "Error creating category.";
                }
            }
        } 
        elseif ($_POST['action'] === 'edit' && !empty($_POST['category_id']) && !empty($_POST['name'])) {
            $category_id = (int)$_POST['category_id'];
            $name = html_entity_decode(sanitizeInput($_POST['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = createSlug($name);
            
            // Check if category name already exists for another category
            $stmt = $conn->prepare("SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id != ?");
            $stmt->bind_param("ssi", $name, $slug, $category_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Category name already exists.";
            } else {
                $sql = "UPDATE categories SET name = ?, slug = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $name, $slug, $category_id);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Category updated successfully!";
                    header('Location: manage-categories.php');
                    exit();
                } else {
                    $error = "Error updating category.";
                }
            }
        }
        elseif ($_POST['action'] === 'delete' && !empty($_POST['category_id'])) {
            $category_id = (int)$_POST['category_id'];
            
            // Check if category has posts
            $stmt = $conn->prepare("SELECT COUNT(*) as post_count FROM posts WHERE category_id = ?");
            $stmt->bind_param("i", $category_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['post_count'] > 0) {
                $error = "Cannot delete category. It has associated posts.";
            } else {
                $sql = "DELETE FROM categories WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $category_id);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Category deleted successfully!";
                    header('Location: manage-categories.php');
                    exit();
                } else {
                    $error = "Error deleting category.";
                }
            }
        }
    }
}

// Get all categories with post count
$sql = "SELECT c.*, COUNT(p.id) as post_count 
        FROM categories c 
        LEFT JOIN posts p ON c.id = p.category_id 
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
                                            <?php if ($category['post_count'] == 0): ?>
                                                <form action="manage-categories.php" method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this category?')">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-danger btn-sm" disabled title="Cannot delete category with associated posts">Delete</button>
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