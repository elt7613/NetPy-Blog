<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$post_id) {
    header('Location: dashboard.php');
    exit;
}

// Get post details
$sql = "SELECT * FROM posts WHERE id = ? AND author_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    header('Location: dashboard.php');
    exit;
}

// Get all categories
$categories = getAllCategories();

// Get all available tags
$sql = "SELECT * FROM tags ORDER BY name";
$result = $conn->query($sql);
$tags = $result->fetch_all(MYSQLI_ASSOC);

// Get post's current tags
$sql = "SELECT tag_id FROM post_tags WHERE post_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$current_tags = array_column($result->fetch_all(MYSQLI_ASSOC), 'tag_id');

// Handle post update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title']);
    $content = $_POST['content']; // Don't sanitize content as it contains HTML
    $category_id = (int)$_POST['category_id'];
    $status = $_POST['status'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $slug = createSlug($title);
    
    // Handle image upload if new image is provided
    $image_path = $post['image_path'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/images/posts/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Delete old image if it exists
        if ($image_path && file_exists('../' . $image_path)) {
            unlink('../' . $image_path);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_extension;
        $target_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_path = 'assets/images/posts/' . $new_filename;
        }
    }
    
    // Update post
    $sql = "UPDATE posts 
            SET title = ?, slug = ?, content = ?, image_path = ?, category_id = ?, status = ?, featured = ? 
            WHERE id = ? AND author_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssissii", $title, $slug, $content, $image_path, $category_id, $status, $featured, $post_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        // Update tags
        // First, remove all existing tags for this post
        $sql = "DELETE FROM post_tags WHERE post_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        
        // Then add the new tags
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

    <title>Edit Post - Stand Blog</title>

    <!-- Bootstrap core CSS -->
    <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Additional CSS Files -->
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <link rel="stylesheet" href="../assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="../assets/css/owl.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
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
            image_title: true,
            image_caption: true,
            image_advtab: true,
            image_uploadtab: true,
            images_file_types: 'jpg,jpeg,png,gif',
            file_picker_callback: (callback, value, meta) => {
                // Provide file and text for the link dialog
                if (meta.filetype === 'file') {
                    callback('mypage.html', { text: 'My text' });
                }

                // Provide image and alt text for the image dialog
                if (meta.filetype === 'image') {
                    const input = document.createElement('input');
                    input.setAttribute('type', 'file');
                    input.setAttribute('accept', 'image/*');

                    input.addEventListener('change', (e) => {
                        const file = e.target.files[0];

                        const reader = new FileReader();
                        reader.addEventListener('load', () => {
                            const id = 'blobid' + (new Date()).getTime();
                            const blobCache = tinymce.activeEditor.editorUpload.blobCache;
                            const base64 = reader.result.split(',')[1];
                            const blobInfo = blobCache.create(id, file, base64);
                            blobCache.add(blobInfo);

                            callback(blobInfo.blobUri(), { 
                                title: file.name,
                                alt: file.name 
                            });
                        });
                        reader.readAsDataURL(file);
                    });

                    input.click();
                }

                // Provide alternative source and posted for the media dialog
                if (meta.filetype === 'media') {
                    callback('movie.mp4', { source2: 'alt.ogg', poster: 'image.jpg' });
                }
            },
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
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
                                <form action="edit-post.php?id=<?php echo $post_id; ?>" method="post" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <fieldset>
                                                <input name="title" type="text" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <select name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $post['category_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <textarea name="content" id="content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-12">
                                            <fieldset>
                                                <?php if ($post['image_path']): ?>
                                                    <img src="../<?php echo htmlspecialchars($post['image_path']); ?>" alt="Current Image" style="max-width: 200px; margin-bottom: 10px;">
                                                    <br>
                                                <?php endif; ?>
                                                <input type="file" name="image" accept="image/*">
                                                <small class="text-muted">Leave empty to keep current image</small>
                                            </fieldset>
                                        </div>
                                        <div class="col-md-6">
                                            <fieldset>
                                                <select name="status" required>
                                                    <option value="draft" <?php echo $post['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                    <option value="published" <?php echo $post['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
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
                                                                <?php echo in_array($tag['id'], $current_tags) ? 'selected' : ''; ?>>
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