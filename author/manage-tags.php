<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit;
}

// Handle tag creation/deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' && !empty($_POST['name'])) {
            $name = html_entity_decode(sanitizeInput($_POST['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = createSlug($name);
            
            // Check if tag already exists
            $stmt = $conn->prepare("SELECT id FROM tags WHERE name = ? OR slug = ?");
            $stmt->bind_param("ss", $name, $slug);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Tag already exists.";
            } else {
                $sql = "INSERT INTO tags (name, slug) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $name, $slug);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Tag created successfully!";
                    header('Location: manage-tags.php');
                    exit();
                } else {
                    $error = "Error creating tag.";
                }
            }
        } 
        elseif ($_POST['action'] === 'edit' && !empty($_POST['tag_id']) && !empty($_POST['name'])) {
            $tag_id = (int)$_POST['tag_id'];
            $name = html_entity_decode(sanitizeInput($_POST['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = createSlug($name);
            
            // Check if tag name already exists for another tag
            $stmt = $conn->prepare("SELECT id FROM tags WHERE (name = ? OR slug = ?) AND id != ?");
            $stmt->bind_param("ssi", $name, $slug, $tag_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = "Tag name already exists.";
            } else {
                $sql = "UPDATE tags SET name = ?, slug = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $name, $slug, $tag_id);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Tag updated successfully!";
                    header('Location: manage-tags.php');
                    exit();
                } else {
                    $error = "Error updating tag.";
                }
            }
        }
        elseif ($_POST['action'] === 'delete' && !empty($_POST['tag_id'])) {
            $tag_id = (int)$_POST['tag_id'];
            
            // Check if tag has posts
            $stmt = $conn->prepare("SELECT COUNT(*) as post_count FROM post_tags WHERE tag_id = ?");
            $stmt->bind_param("i", $tag_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['post_count'] > 0) {
                $error = "Cannot delete tag. It has associated posts.";
            } else {
                $sql = "DELETE FROM tags WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $tag_id);
                if ($stmt->execute()) {
                    $_SESSION['success_msg'] = "Tag deleted successfully!";
                    header('Location: manage-tags.php');
                    exit();
                } else {
                    $error = "Error deleting tag.";
                }
            }
        }
    }
}

// Get all tags with post count
$sql = "SELECT t.*, COUNT(pt.post_id) as post_count 
        FROM tags t 
        LEFT JOIN post_tags pt ON t.id = pt.tag_id 
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
                                            <?php if ($tag['post_count'] == 0): ?>
                                                <form action="manage-tags.php" method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this tag?')">Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-danger btn-sm" disabled title="Cannot delete tag with associated posts">Delete</button>
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