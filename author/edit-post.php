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

$page_title = "Edit Post";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">

    <title>Edit Post - NetPy Blog</title>

    <!-- Bootstrap core CSS -->
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Additional CSS Files -->
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container {
            width: 100% !important;
        }
        .select2-container .select2-selection--single {
            height: 45px;
            padding: 10px;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 25px;
            color: #666;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px;
        }
        .select2-dropdown {
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #f48840;
        }
        .select2-dropdown-large {
            padding: 10px;
        }
        .select2-dropdown-large .select2-results__option {
            padding: 8px;
            margin: 2px 0;
            border-radius: 4px;
        }
        .select2-dropdown-large .select2-results__option:hover {
            background-color: #f7f7f7;
        }
        .select2-dropdown-large .select2-results__option[aria-selected=true] {
            background-color: #e9ecef;
        }
        .select2-container--default .select2-selection--multiple {
            border-color: #ced4da;
            padding: 5px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #f48840;
            border: none;
            color: white;
            padding: 5px 10px;
            margin: 2px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 5px;
        }
        .current-image {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
        .current-image img {
            max-width: 200px;
            height: auto;
            display: block;
            margin: 0 auto;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-control {
            height: 45px;
            font-size: 14px;
        }
        textarea.form-control {
            height: auto;
            min-height: 200px;
        }
        .btn {
            height: 45px;
            padding: 0 30px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-primary {
            background-color: #f48840;
            border-color: #f48840;
        }
        .btn-primary:hover {
            background-color: #d67736;
            border-color: #d67736;
        }
        .custom-control-input:checked ~ .custom-control-label::before {
            background-color: #f48840;
            border-color: #f48840;
        }
    </style>

    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/5/tinymce.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
                            <h2>Edit Post</h2>
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
                                <h2>Edit Post</h2>
                            </div>
                            <div class="content">
                                <?php if (!empty($errors)): ?>
                                    <div class="alert alert-danger">
                                        <ul>
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <form id="post-form" action="edit-post.php?id=<?php echo $post_id; ?>" method="post" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <fieldset>
                                                <input name="title" type="text" placeholder="Post Title" required
                                                    value="<?php echo htmlspecialchars($post['title']); ?>">
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <select name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>"
                                                            <?php echo $post['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <div class="form-group">
                                                    <label>Post Content</label>
                                                    <textarea name="content" id="content" class="form-control" rows="10"><?php echo htmlspecialchars($post['content']); ?></textarea>
                                                </div>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <?php if ($post['image_path']): ?>
                                                    <div class="current-image">
                                                        <img src="<?php echo '../' . $post['image_path']; ?>" alt="Current featured image">
                                                    </div>
                                                <?php endif; ?>
                                                <input type="file" name="image" accept="image/*">
                                                <small class="text-muted">Leave empty to keep current image</small>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <select name="status" required>
                                                    <option value="draft" <?php echo $post['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="published" <?php echo $post['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                                                </select>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>
                                                    <input type="checkbox" name="featured" <?php echo $post['featured'] ? 'checked' : ''; ?>> Featured Post
                                                </label>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <label>Select Tags:</label>
                                                <select name="tags[]" multiple class="form-control select2-tags">
                                                    <?php foreach ($tags as $tag): ?>
                                                        <option value="<?php echo $tag['id']; ?>"
                                                            <?php echo in_array($tag['id'], $current_tag_ids) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($tag['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">You can select multiple tags</small>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" id="form-submit" class="main-button">Update Post</button>
                                                <form action="delete-post.php" method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post? This action cannot be undone.');">
                                                    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                                    <button type="submit" class="btn btn-danger">Delete Post</button>
                                                </form>
                                            </fieldset>
                                        </div>
                                    </div>
                                </form>
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
        document.addEventListener('DOMContentLoaded', function() {
            tinymce.init({
                selector: '#content',
                plugins: [
                    'advlist autolink lists link image charmap print preview anchor',
                    'searchreplace visualblocks code fullscreen',
                    'insertdatetime media table paste code help wordcount'
                ],
                toolbar: 'undo redo | formatselect | bold italic backcolor | \
                    alignleft aligncenter alignright alignjustify | \
                    bullist numlist outdent indent | removeformat | image | help',
                height: 400,
                images_upload_url: '../upload.php',
                automatic_uploads: true,
                images_reuse_filename: true,
                relative_urls: false,
                remove_script_host: false,
                convert_urls: true,
                file_picker_types: 'image',
                images_upload_handler: function (blobInfo, success, failure) {
                    var xhr, formData;
                    xhr = new XMLHttpRequest();
                    xhr.withCredentials = false;
                    xhr.open('POST', '../upload.php');
                    xhr.onload = function() {
                        var json;
                        if (xhr.status != 200) {
                            failure('HTTP Error: ' + xhr.status);
                            return;
                        }
                        json = JSON.parse(xhr.responseText);
                        if (!json || typeof json.location != 'string') {
                            failure('Invalid JSON: ' + xhr.responseText);
                            return;
                        }
                        success(json.location);
                    };
                    formData = new FormData();
                    formData.append('file', blobInfo.blob(), blobInfo.filename());
                    xhr.send(formData);
                },
                init_instance_callback: function(editor) {
                    editor.on('Change', function(e) {
                        editor.save();
                    });
                }
            });

            // Handle form submission
            document.getElementById('post-form').addEventListener('submit', function(e) {
                e.preventDefault();
                tinymce.triggerSave();
                this.submit();
            });
        });

        // Initialize Select2 for tags
        $(document).ready(function() {
            $('.select2-tags').select2({
                placeholder: 'Select tags',
                allowClear: true,
                closeOnSelect: false,
                templateResult: function(data) {
                    if (!data.id) return data.text;
                    return $('<div><input type="checkbox" ' + 
                        ($(data.element).is(':selected') ? 'checked' : '') + 
                        '/> ' + data.text + '</div>');
                }
            });
        });

        // Hide preloader when page is loaded
        window.addEventListener('load', function() {
            document.getElementById('preloader').style.display = 'none';
        });
    </script>
</body>
</html> 