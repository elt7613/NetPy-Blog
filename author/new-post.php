<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit();
}

// Get categories for the dropdown
$categories = $conn->query("SELECT * FROM categories WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get tags for the dropdown
$tags = $conn->query("SELECT * FROM tags WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = $_POST['content'];
    $category_id = (int)$_POST['category_id'];
    $status = $_POST['status'];
    $featured = isset($_POST['featured']) ? 1 : 0;
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
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/images/posts/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif','webp','avif','svg','ico'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
        } else {
            $new_filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = 'assets/images/posts/' . $new_filename;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }

    if (empty($errors)) {
        // Generate slug from title
        $slug = createSlug($title);
        
        // Check if slug exists
        $stmt = $conn->prepare("SELECT id FROM posts WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $slug = $slug . '-' . uniqid();
        }

        // Create the post
        $stmt = $conn->prepare("INSERT INTO posts (title, slug, content, image_path, category_id, author_id, status, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssiisi", $title, $slug, $content, $image_path, $category_id, $_SESSION['user_id'], $status, $featured);
        
        if ($stmt->execute()) {
            $post_id = $stmt->insert_id;
            
            // Add tags
            if (!empty($selected_tags)) {
                $tag_sql = "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)";
                $tag_stmt = $conn->prepare($tag_sql);
                foreach ($selected_tags as $tag_id) {
                    $tag_id = (int)$tag_id;
                    $tag_stmt->bind_param("ii", $post_id, $tag_id);
                    $tag_stmt->execute();
                }
            }
            
            // If post is published, send newsletter emails
            if ($status === 'published') {
                // Get all active newsletter subscribers
                $subscriber_sql = "SELECT email FROM netpy_newsletter_users WHERE deleted_at IS NULL AND is_active = 1";
                $subscriber_result = $conn->query($subscriber_sql);
                
                if ($subscriber_result->num_rows > 0) {
                    require_once '../email.php';
                    
                    // Get the full URL for the blog post
                    $post_url = "http://" . $_SERVER['HTTP_HOST'] . "/post-details.php?slug=" . urlencode($slug);
                    
                    // Create email content
                    $subject = "New Blog Post: " . $title;
                    
                    // Create HTML email body
                    $htmlBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                            <h2 style='color: #f48840;'>New Blog Post Published!</h2>
                            <h3>{$title}</h3>";
                    
                    // Add image if exists
                    if (!empty($image_path)) {
                        $image_url = "http://" . $_SERVER['HTTP_HOST'] . "/" . $image_path;
                        $htmlBody .= "<img src='{$image_url}' style='max-width: 100%; height: auto; margin: 20px 0;' alt='{$title}'>";
                    }
                    
                    $htmlBody .= "
                            <div style='margin: 20px 0;'>
                                " . substr(strip_tags($content), 0, 200) . "...
                            </div>
                            <a href='{$post_url}' style='display: inline-block; padding: 10px 20px; background-color: #f48840; color: white; text-decoration: none; border-radius: 5px;'>Read More</a>
                            <p style='margin-top: 20px; font-size: 12px; color: #666;'>
                                You received this email because you're subscribed to our newsletter. 
                                <a href='http://" . $_SERVER['HTTP_HOST'] . "/unsubscribe.php'>Unsubscribe</a>
                            </p>
                        </div>";
                    
                    // Send email to each subscriber
                    while ($subscriber = $subscriber_result->fetch_assoc()) {
                        sendEmail(
                            $subscriber['email'],
                            '',
                            $subject,
                            $htmlBody
                        );
                    }
                }
            }

            $_SESSION['success_msg'] = "Post created successfully!";
            header('Location: dashboard.php');
            exit();
        } else {
            $errors[] = "Error creating post: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Post - NetPy Blog</title>
    
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
                            <h2 class="mb-4">Create New Post</h2>
                            
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form action="" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="title">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="content">Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="10"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category_id">Category</label>
                                    <select class="form-control" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tags">Tags</label>
                                    <select class="form-control" id="tags" name="tags[]" multiple>
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo $tag['id']; ?>">
                                                <?php echo htmlspecialchars($tag['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="image">Featured Image</label>
                                    <input type="file" class="form-control-file" id="image" name="image" accept="image/*" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="draft">Draft</option>
                                        <option value="published">Published</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="featured" name="featured">
                                        <label class="custom-control-label" for="featured">Featured Post</label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Create Post</button>
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