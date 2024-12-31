<?php
require_once 'config.php';
require_once 'functions.php';

// Get post slug from URL
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';
if (empty($slug)) {
    header('Location: index.php');
    exit;
}

// Get post details
$post = getPostBySlug($slug);
if (!$post) {
    header('Location: index.php');
    exit;
}

// Check if current user is the author of the post
$isAuthor = isLoggedIn() && $_SESSION['user_id'] == $post['author_id'];

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment']) && (isAdmin() || $isAuthor)) {
    $comment_id = (int)$_POST['comment_id'];
    
    // Delete the comment and its replies
    $sql = "DELETE FROM comments WHERE id = ? OR parent_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $comment_id, $comment_id);
    $stmt->execute();
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?slug=" . urlencode($slug) . "#comments");
    exit;
}

// Update view count
$sql = "UPDATE posts SET views = views + 1 WHERE slug = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $slug);
$stmt->execute();

// Get approved comments only
$sql = "SELECT c.*, u.username, u.avatar 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.post_id = ? 
        ORDER BY c.parent_id ASC, c.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $post['id']);
$stmt->execute();
$all_comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Organize comments into a tree structure
$comments = [];
$comment_replies = [];
foreach ($all_comments as $comment) {
    if ($comment['parent_id'] === null) {
        $comments[] = $comment;
    } else {
        if (!isset($comment_replies[$comment['parent_id']])) {
            $comment_replies[$comment['parent_id']] = [];
        }
        $comment_replies[$comment['parent_id']][] = $comment;
    }
}

// Function to display nested comments
function displayComment($comment, $comment_replies, $level = 0) {
    global $post, $isAuthor;
    $has_replies = isset($comment_replies[$comment['id']]) && !empty($comment_replies[$comment['id']]);
    $reply_count = $has_replies ? count($comment_replies[$comment['id']]) : 0;
    ?>
    <div class="comment-item mb-4">
        <div class="comment-container <?php echo $level > 0 ? 'is-reply' : ''; ?>">
            <?php if ($level > 0): ?>
                <div class="reply-indicator"></div>
            <?php endif; ?>
            <div class="d-flex w-100">
                <div class="author-thumb mr-3">
                    <?php if (!empty($comment['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($comment['avatar']); ?>" alt="<?php echo htmlspecialchars($comment['username']); ?>">
                    <?php else: ?>
                        <img src="assets/images/deffault-profile-img.png" alt="Default profile">
                    <?php endif; ?>
                </div>
                <div class="comment-content flex-grow-1">
                    <div class="comment-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo htmlspecialchars($comment['username']); ?></h4>
                            <span class="text-muted"><?php echo date('M d, Y \a\t h:i A', strtotime($comment['created_at'])); ?></span>
                        </div>
                        <div class="comment-actions">
                            <?php if (isLoggedIn()): ?>
                                <button class="btn btn-sm btn-link reply-btn" 
                                        onclick="showReplyForm(<?php echo $comment['id']; ?>)">
                                    Reply
                                </button>
                            <?php endif; ?>
                            <?php if (isAdmin() || $isAuthor): ?>
                                <form action="post-details.php?slug=<?php echo urlencode($_GET['slug']); ?>#comments" 
                                      method="post" 
                                      class="d-inline" 
                                      onsubmit="return confirm('Are you sure you want to delete this comment and all its replies?');">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <button type="submit" 
                                            name="delete_comment" 
                                            class="btn btn-sm btn-link text-danger delete-btn">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                    
                    <!-- Reply form (hidden by default) -->
                    <?php if (isLoggedIn()): ?>
                        <div id="reply-form-<?php echo $comment['id']; ?>" class="reply-form mt-3" style="display: none;">
                            <form action="post-details.php?slug=<?php echo urlencode($_GET['slug']); ?>#comments" method="post">
                                <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                <div class="form-group">
                                    <textarea name="comment" class="form-control" rows="3" placeholder="Write your reply..." required></textarea>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary btn-sm">Submit Reply</button>
                                    <button type="button" class="btn btn-secondary btn-sm" 
                                            onclick="hideReplyForm(<?php echo $comment['id']; ?>)">Cancel</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if ($has_replies): ?>
                        <div class="replies-section mt-3">
                            <button class="btn btn-sm btn-link toggle-replies" 
                                    onclick="toggleReplies(<?php echo $comment['id']; ?>)"
                                    id="toggle-btn-<?php echo $comment['id']; ?>">
                                <i class="fa fa-caret-right" id="caret-<?php echo $comment['id']; ?>"></i>
                                Show <?php echo $reply_count; ?> <?php echo $reply_count === 1 ? 'reply' : 'replies'; ?>
                            </button>
                            <div id="replies-<?php echo $comment['id']; ?>" class="replies-container" style="display: none;">
                                <?php foreach ($comment_replies[$comment['id']] as $reply): ?>
                                    <?php displayComment($reply, $comment_replies, $level + 1); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $comment_content = sanitizeInput($_POST['comment']);
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    if (!empty($comment_content)) {
        addComment($post['id'], $_SESSION['user_id'], $comment_content, $parent_id);
        $_SESSION['comment_success'] = true;
        header("Location: post-details.php?slug=" . urlencode($slug) . "#comments");
        exit;
    }
}

// Get categories for sidebar
$categories = getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="<?php echo substr(strip_tags($post['content']), 0, 160); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($post['author_name']); ?>">
    <link href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,300i,400,400i,500,500i,700,700i,900,900i&display=swap" rel="stylesheet">

    <title><?php echo htmlspecialchars($post['title']); ?> - Stand Blog</title>

    <!-- Bootstrap core CSS -->
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Additional CSS Files -->
    <link rel="stylesheet" href="assets/css/fontawesome.css">
    <link rel="stylesheet" href="assets/css/templatemo-stand-blog.css">
    <link rel="stylesheet" href="assets/css/owl.css">
    <link rel="stylesheet" href="assets/css/accordions.js">
    <style>
        .share-menu {
            min-width: 200px;
            padding: 10px 0;
        }
        .share-menu .dropdown-item {
            padding: 8px 20px;
            color: #333;
            font-size: 14px;
        }
        .share-menu .dropdown-item i {
            width: 25px;
            text-align: center;
        }
        .share-menu .dropdown-item:hover {
            background-color: #f48840;
            color: #fff;
        }
        .share-menu .dropdown-divider {
            margin: 5px 0;
        }
        .copy-success {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            display: none;
            z-index: 1000;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .author-thumb img {
            border-radius: 50%;
            width: 60px;
            height: 60px;
            object-fit: cover;
        }
        .comment-item {
            width: 100%;
            margin-bottom: 20px;
        }
        .comment-container {
            position: relative;
            width: 100%;
        }
        .comment-container.is-reply {
            padding-left: 40px;
        }
        .reply-indicator {
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #f48840;
            opacity: 0.5;
        }
        .comment-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            width: 100%;
        }
        .replies-container {
            position: relative;
            width: 100%;
            margin-top: 15px;
        }
        .replies-container:before {
            content: '';
            position: absolute;
            left: -20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #f48840;
            opacity: 0.3;
        }
        .author-thumb {
            flex-shrink: 0;
        }
        .flex-grow-1 {
            flex-grow: 1;
            min-width: 0;
        }
        .comment-header {
            margin-bottom: 10px;
        }
        .comment-header h4 {
            margin: 0;
            color: #f48840;
            font-size: 18px;
        }
        .comment-header span {
            font-size: 14px;
        }
        .comment-content p {
            margin: 0;
            line-height: 1.6;
        }
        .author-thumb img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .submit-comment textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .submit-comment .main-button {
            background-color: #f48840;
            color: #fff;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .submit-comment .main-button:hover {
            background-color: #fb9857;
        }
        .reply-form {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .reply-btn {
            color: #f48840;
            padding: 0;
            font-size: 14px;
        }
        .reply-btn:hover {
            color: #fb9857;
            text-decoration: none;
        }
        .toggle-replies {
            color: #6c757d;
            padding: 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .toggle-replies:hover {
            color: #f48840;
            text-decoration: none;
        }
        .toggle-replies i {
            transition: transform 0.3s ease;
        }
        .toggle-replies.active i {
            transform: rotate(90deg);
        }
        .replies-container {
            margin-top: 15px;
        }
        .comment-reply {
            position: relative;
            padding-left: 40px;
        }
        .reply-indicator {
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #f48840;
            opacity: 0.5;
        }
        .comment-wrapper {
            position: relative;
        }
        .replies-container {
            margin-top: 15px;
            position: relative;
        }
        .replies-container:before {
            content: '';
            position: absolute;
            left: -20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #f48840;
            opacity: 0.3;
        }
        .comment-item {
            position: relative;
            width: 100%;
        }
        .comment-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .delete-btn {
            color: #dc3545;
            padding: 0;
            font-size: 14px;
            margin-left: 10px;
        }
        .delete-btn:hover {
            color: #c82333;
            text-decoration: none;
        }
        .comment-actions {
            display: flex;
            align-items: center;
        }
    </style>
</head>

<body>
    <!-- Add this div for copy success message -->
    <div class="copy-success">Link copied to clipboard!</div>

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
    <?php include 'includes/header.php'; ?>

    <!-- Page Content -->
    <!-- Banner Starts Here -->
    <div class="heading-page header-text">
        <section class="page-heading">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-content">
                            <h4><?php echo htmlspecialchars($post['category_name']); ?></h4>
                            <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <section class="blog-posts grid-system">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="all-blog-posts">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="blog-post">
                                    <div class="blog-thumb">
                                        <?php if (!empty($post['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="img-fluid">
                                        <?php else: ?>
                                            <img src="assets/images/default-post.jpg" alt="Default post image" class="img-fluid">
                                        <?php endif; ?>
                                    </div>
                                    <div class="down-content">
                                        <span><?php echo htmlspecialchars($post['category_name']); ?></span>
                                        <ul class="post-info">
                                            <li><a href="#"><?php echo htmlspecialchars($post['author_name']); ?></a></li>
                                            <li><a href="#"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></a></li>
                                            <li><a href="#"><?php echo $post['views']; ?> Views</a></li>
                                        </ul>
                                        <div class="post-content">
                                            <?php echo $post['content']; ?>
                                        </div>
                                        <div class="post-options">
                                            <div class="row">
                                                <div class="col-6">
                                                    <ul class="post-tags">
                                                        <li><i class="fa fa-tags"></i></li>
                                                        <?php
                                                        // Get post tags
                                                        $sql = "SELECT t.name, t.slug 
                                                                FROM tags t 
                                                                JOIN post_tags pt ON t.id = pt.tag_id 
                                                                WHERE pt.post_id = ?";
                                                        $stmt = $conn->prepare($sql);
                                                        $stmt->bind_param("i", $post['id']);
                                                        $stmt->execute();
                                                        $tags = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                                        
                                                        if (!empty($tags)) {
                                                            foreach ($tags as $index => $tag) {
                                                                echo '<li><a href="tag.php?slug=' . urlencode($tag['slug']) . '">' . 
                                                                     htmlspecialchars($tag['name']) . '</a>';
                                                                if ($index < count($tags) - 1) {
                                                                    echo ', ';
                                                                }
                                                                echo '</li>';
                                                            }
                                                        } else {
                                                            echo '<li>No tags</li>';
                                                        }
                                                        ?>
                                                    </ul>
                                                </div>
                                                <div class="col-6">
                                                    <ul class="post-share">
                                                        <li><i class="fa fa-share-alt"></i></li>
                                                        <li class="dropdown">
                                                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                                                Share
                                                            </a>
                                                            <div class="dropdown-menu dropdown-menu-right share-menu">
                                                                <?php
                                                                $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                                                                $title = urlencode($post['title']);
                                                                $url = urlencode($current_url);
                                                                ?>
                                                                <a class="dropdown-item" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>" target="_blank">
                                                                    <i class="fa fa-facebook"></i> Facebook
                                                                </a>
                                                                <a class="dropdown-item" href="https://twitter.com/intent/tweet?url=<?php echo $url; ?>&text=<?php echo $title; ?>" target="_blank">
                                                                    <i class="fa fa-twitter"></i> Twitter
                                                                </a>
                                                                <a class="dropdown-item" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $url; ?>&title=<?php echo $title; ?>" target="_blank">
                                                                    <i class="fa fa-linkedin"></i> LinkedIn
                                                                </a>
                                                                <a class="dropdown-item" href="https://api.whatsapp.com/send?text=<?php echo $title . ' ' . $url; ?>" target="_blank">
                                                                    <i class="fa fa-whatsapp"></i> WhatsApp
                                                                </a>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item copy-link" href="#" data-url="<?php echo htmlspecialchars($current_url); ?>">
                                                                    <i class="fa fa-link"></i> Copy Link
                                                                </a>
                                                            </div>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Comments Section -->
                            <div class="col-lg-12" id="comments">
                                <div class="sidebar-item comments">
                                    <div class="sidebar-heading">
                                        <h2><?php echo count($comments); ?> comments</h2>
                                    </div>

                                    <?php if (isset($_SESSION['comment_success'])): ?>
                                        <div class="alert alert-success">
                                            Your comment has been posted successfully!
                                        </div>
                                        <?php unset($_SESSION['comment_success']); ?>
                                    <?php endif; ?>

                                    <div class="content">
                                        <?php if (!empty($comments)): ?>
                                            <?php foreach ($comments as $comment): ?>
                                                <?php displayComment($comment, $comment_replies); ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p>No comments yet. Be the first to comment!</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (isLoggedIn()): ?>
                                    <div class="sidebar-item submit-comment">
                                        <div class="sidebar-heading">
                                            <h2>Leave a comment</h2>
                                        </div>
                                        <div class="content">
                                            <form action="post-details.php?slug=<?php echo urlencode($slug); ?>#comments" method="post">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <fieldset>
                                                            <textarea name="comment" rows="6" placeholder="Type your comment" required></textarea>
                                                        </fieldset>
                                                    </div>
                                                    <div class="col-lg-12">
                                                        <fieldset>
                                                            <button type="submit" class="main-button">Submit Comment</button>
                                                        </fieldset>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="sidebar-item">
                                        <div class="content">
                                            <p><a href="login.php">Login</a> to leave a comment.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <?php include 'includes/sidebar.php'; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Bootstrap core JavaScript -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Additional Scripts -->
    <script src="assets/js/custom.js"></script>
    <script src="assets/js/owl.js"></script>
    <script src="assets/js/slick.js"></script>
    <script src="assets/js/isotope.js"></script>
    <script src="assets/js/accordions.js"></script>

    <script>
        $(document).ready(function() {
            // Handle copy link
            $('.copy-link').click(function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                
                // Create temporary input
                var $temp = $("<input>");
                $("body").append($temp);
                $temp.val(url).select();
                
                // Copy to clipboard
                document.execCommand("copy");
                $temp.remove();
                
                // Show success message
                $('.copy-success').fadeIn().delay(2000).fadeOut();
            });

            // Close dropdown when clicking outside
            $(document).click(function(e) {
                if (!$(e.target).closest('.dropdown').length) {
                    $('.share-menu').removeClass('show');
                }
            });
        });

        function toggleReplies(commentId) {
            const repliesContainer = document.getElementById('replies-' + commentId);
            const toggleButton = document.getElementById('toggle-btn-' + commentId);
            const caret = document.getElementById('caret-' + commentId);
            
            if (repliesContainer.style.display === 'none') {
                repliesContainer.style.display = 'block';
                toggleButton.classList.add('active');
                toggleButton.innerHTML = `<i class="fa fa-caret-right" id="caret-${commentId}"></i> Hide replies`;
            } else {
                repliesContainer.style.display = 'none';
                toggleButton.classList.remove('active');
                const replyCount = repliesContainer.children.length;
                toggleButton.innerHTML = `<i class="fa fa-caret-right" id="caret-${commentId}"></i> Show ${replyCount} ${replyCount === 1 ? 'reply' : 'replies'}`;
            }
        }

        function showReplyForm(commentId) {
            document.getElementById('reply-form-' + commentId).style.display = 'block';
        }

        function hideReplyForm(commentId) {
            document.getElementById('reply-form-' + commentId).style.display = 'none';
        }
    </script>
</body>
</html> 