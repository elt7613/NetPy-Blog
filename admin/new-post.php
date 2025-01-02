<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';
require_once '../functions.php';

// Debugging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Form submitted. POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
}

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get all categories
$categories = getAllCategories();

// Get all tags
$sql = "SELECT * FROM tags ORDER BY name";
$result = $conn->query($sql);
$tags = $result->fetch_all(MYSQLI_ASSOC);

// Handle post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Debug output
    echo "<pre>";
    echo "POST Data:\n";
    print_r($_POST);
    echo "\nFILES Data:\n";
    print_r($_FILES);
    echo "</pre>";
    
    // Validate required fields
    if (empty($_POST['title'])) {
        $errors[] = "Title is required";
    }
    if (empty($_POST['content'])) {
        $errors[] = "Content is required";
    }
    if (empty($_POST['category_id'])) {
        $errors[] = "Category is required";
    }
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Featured image is required";
    }

    // If no errors, process the submission
    if (empty($errors)) {
        try {
            $title = sanitizeInput($_POST['title']);
            $content = $_POST['content']; // Don't sanitize content as it contains HTML
            $category_id = (int)$_POST['category_id'];
            $status = $_POST['status'];
            $featured = isset($_POST['featured']) ? 1 : 0;
            $slug = createSlug($title);
            
            // Handle image upload
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/images/posts/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_extensions));
                }
                
                $new_filename = uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_path = 'assets/images/posts/' . $new_filename;
                } else {
                    throw new Exception("Failed to upload image: " . error_get_last()['message']);
                }
            }
            
            // Insert post
            $sql = "INSERT INTO posts (title, slug, content, image_path, category_id, author_id, status, featured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("ssssissi", $title, $slug, $content, $image_path, $category_id, $_SESSION['user_id'], $status, $featured);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            // Handle tags
            $post_id = $conn->insert_id;
            if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                $sql = "INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                
                foreach ($_POST['tags'] as $tag_id) {
                    $tag_id = (int)$tag_id;
                    $stmt->bind_param("ii", $post_id, $tag_id);
                    $stmt->execute();
                }
            }
            
            header('Location: dashboard.php');
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
            error_log("New post error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">

    <title>New Post - NetPy Blog</title>

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
    </style>
    
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/<?php echo TINYMCE_API_KEY; ?>/tinymce/5/tinymce.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for tags
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
    </script>
</head>

<body>
    <!-- ***** Preloader Start ***** -->
    <div id="preloader">
        <div class="jumper">
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>  
    <!-- ***** Preloader End ***** -->

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
                            <h2>Create New Post</h2>
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
                                <h2>New Post</h2>
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
                                <form id="post-form" action="new-post.php" method="post" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <fieldset>
                                                <input name="title" type="text" placeholder="Post Title" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <select name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>">
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <textarea name="content" id="content" placeholder="Post Content" required></textarea>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <input type="file" name="image" accept="image/*" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <select name="status" required>
                                                    <option value="draft">Draft</option>
                                                    <option value="published">Published</option>
                                                </select>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <label>
                                                    <input type="checkbox" name="featured"> Featured Post
                                                </label>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <label>Select Tags:</label>
                                                <select name="tags[]" multiple class="form-control select2-tags">
                                                    <?php foreach ($tags as $tag): ?>
                                                        <option value="<?php echo $tag['id']; ?>">
                                                            <?php echo htmlspecialchars($tag['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">You can select multiple tags</small>
                                            </fieldset>
                                        </div>
                                        <div class="col-lg-12">
                                            <fieldset>
                                                <button type="submit" id="form-submit" class="main-button">Create Post</button>
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

    <!-- Bootstrap core JavaScript -->
    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Additional Scripts -->
    <script src="../assets/js/custom.js"></script>
    <script src="../assets/js/owl.js"></script>
    <script src="../assets/js/slick.js"></script>
    <script src="../assets/js/isotope.js"></script>
    <script src="../assets/js/accordions.js"></script>
</body>
</html> 