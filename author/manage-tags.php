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
                $stmt = $conn->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $slug);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Tag added successfully!";
                } else {
                    $error = "Error adding tag.";
                }
                $stmt->close();
            }
            break;
            
        case 'edit':
            $tag_id = $_POST['tag_id'];
            $name = trim($_POST['name']);
            if (!empty($name) && !empty($tag_id)) {
                $slug = createSlug($name);
                $stmt = $conn->prepare("UPDATE tags SET name = ?, slug = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $slug, $tag_id);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Tag updated successfully!";
                } else {
                    $error = "Error updating tag.";
                }
                $stmt->close();
            }
            break;
            
        case 'deactivate':
            $tag_id = $_POST['tag_id'];
            if (!empty($tag_id)) {
                $stmt = $conn->prepare("UPDATE tags SET is_active = 0 WHERE id = ?");
                $stmt->bind_param("i", $tag_id);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Tag deactivated successfully!";
                } else {
                    $error = "Error deactivating tag.";
                }
                $stmt->close();
            }
            break;
    }
    
    // Redirect to refresh the page
    header('Location: manage-tags.php');
    exit;
}

// Get all active tags with post count
$sql = "SELECT t.*, COUNT(pt.post_id) as post_count 
        FROM tags t 
        LEFT JOIN post_tags pt ON t.id = pt.tag_id 
        LEFT JOIN posts p ON pt.post_id = p.id AND p.deleted_at IS NULL
        WHERE t.is_active = 1 AND t.deleted_at IS NULL
        GROUP BY t.id 
        ORDER BY t.name";
$result = $conn->query($sql);
$tags = $result->fetch_all(MYSQLI_ASSOC);

$page_title = "Manage Tags";
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
                        <h2>Manage Tags</h2>
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
                        <h4>Add New Tag</h4>
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

                        <form action="manage-tags.php" method="post">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <input name="name" type="text" class="form-control" placeholder="Tag Name" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Tag</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h4>Existing Tags</h4>
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
                                    <?php foreach ($tags as $tag): ?>
                                    <tr>
                                        <td>
                                            <span class="tag-name" data-id="<?php echo $tag['id']; ?>">
                                                <?php echo $tag['name']; ?>
                                            </span>
                                            <form action="manage-tags.php" method="post" class="edit-form" style="display: none;">
                                                <input type="hidden" name="action" value="edit">
                                                <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                                <div class="input-group">
                                                    <input type="text" name="name" value="<?php echo $tag['name']; ?>" class="form-control">
                                                    <div class="input-group-append">
                                                        <button type="submit" class="btn btn-success btn-sm">Save</button>
                                                        <button type="button" class="btn btn-secondary btn-sm cancel-edit">Cancel</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                        <td><?php echo htmlspecialchars($tag['slug']); ?></td>
                                        <td><?php echo $tag['post_count']; ?></td>
                                        <td>
                                            <button class="btn btn-primary btn-sm edit-tag">Edit</button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="deactivate">
                                                <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to deactivate this tag?');">
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
        // Edit tag
        $('.edit-tag').click(function() {
            var row = $(this).closest('tr');
            row.find('.tag-name').hide();
            row.find('.edit-form').show();
            $(this).hide();
        });

        // Cancel edit
        $('.cancel-edit').click(function() {
            var row = $(this).closest('tr');
            row.find('.edit-form').hide();
            row.find('.tag-name').show();
            row.find('.edit-tag').show();
        });
    });
</script>

<?php include '../includes/footer.php'; ?> 