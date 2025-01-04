<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit();
}

// Check if post ID is provided
if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$post_id = $_GET['id'];

// Get post data and verify ownership
$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ? AND author_id = ?");
$stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    $_SESSION['error_msg'] = "Post not found or you don't have permission to edit it.";
    header('Location: dashboard.php');
    exit();
}

// Get categories for the dropdown
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get tags for the dropdown
$tags = $conn->query("SELECT * FROM tags ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get current post tags
$stmt = $conn->prepare("SELECT tag_id FROM post_tags WHERE post_id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$current_tags = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$current_tag_ids = array_column($current_tags, 'tag_id');

// Handle post update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $category_id = (int)$_POST['category_id'];
    $status = $_POST['status'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $slug = createSlug($title);
    $selected_tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    $errors = [];

    // Validate input
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    if (empty($content)) {
        $errors[] = "Content is required";
    }
    if (empty($category_id)) {
        $errors[] = "Category is required";
    }

    // Handle image upload
    $image_path = $post['image_path'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/images/posts/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Delete old image if it exists
        if ($image_path && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
        } else {
            $new_filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = 'assets/images/posts/' . $new_filename;
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        }
    }

    if (empty($errors)) {
        // Update the post
        $stmt = $conn->prepare("UPDATE posts SET title = ?, slug = ?, content = ?, category_id = ?, status = ?, featured = ?, image_path = ? WHERE id = ? AND author_id = ?");
        $stmt->bind_param("sssisssii", $title, $slug, $content, $category_id, $status, $featured, $image_path, $post_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            // Update tags
            $conn->query("DELETE FROM post_tags WHERE post_id = $post_id");
            if (!empty($selected_tags)) {
                $tag_sql = "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)";
                $tag_stmt = $conn->prepare($tag_sql);
                foreach ($selected_tags as $tag_id) {
                    $tag_id = (int)$tag_id;
                    $tag_stmt->bind_param("ii", $post_id, $tag_id);
                    $tag_stmt->execute();
                }
            }
            
            $_SESSION['success_msg'] = "Post updated successfully!";
            header('Location: dashboard.php');
            exit();
        } else {
            $errors[] = "Error updating post: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post - NetPy Blog</title>
    
    <!-- Bootstrap CSS -->
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Additional CSS -->
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content" style="padding-top: 150px;">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h2 class="mb-4">Edit Post</h2>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form action="edit-post.php?id=<?php echo $post_id; ?>" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="title">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="content">Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="10"><?php echo htmlspecialchars($post['content']); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category_id">Category</label>
                                    <select class="form-control" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $post['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tags">Tags</label>
                                    <select class="form-control" id="tags" name="tags[]" multiple>
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo $tag['id']; ?>" <?php echo in_array($tag['id'], $current_tag_ids) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tag['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="image">Featured Image</label>
                                    <?php if ($post['image_path']): ?>
                                        <div class="current-image mb-2">
                                            <img src="<?php echo '../' . $post['image_path']; ?>" alt="Current featured image" style="max-width: 200px;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                                    <small class="text-muted">Leave empty to keep current image</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="featured" name="featured" <?php echo $post['featured'] ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="featured">Featured Post</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Post</button>
                                <button type="button" class="btn btn-danger" onclick="if(confirm('Are you sure you want to delete this post? This action cannot be undone.')) window.location.href='delete-post.php?id=<?php echo $post_id; ?>'">Delete Post</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <!-- Bootstrap core JavaScript -->
    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#content',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount checklist mediaembed casechange export formatpainter pageembed linkchecker a11ychecker tinymcespellchecker permanentpen powerpaste advtable advcode editimage tinycomments tableofcontents footnotes mergetags autocorrect typography inlinecss',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat',
            tinycomments_mode: 'embedded',
            tinycomments_author: 'Author name',
            mergetags_list: [
                { value: 'First.Name', title: 'First Name' },
                { value: 'Email', title: 'Email' },
            ],
            height: 500,
            menubar: false,
            promotion: false
        });
        
        // Initialize Select2
        $(document).ready(function() {
            $('#category_id').select2();
            $('#tags').select2({
                placeholder: 'Select tags',
                allowClear: true
            });
        });
    </script>
</body>
</html> 