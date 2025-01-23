<?php
require_once 'config.php';
require_once 'functions.php';

// Get post slug from URL
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';
if (empty($slug)) {
    header('Location: home.php');
    exit;
}

// Get post details
$post = getPostBySlug($slug);
if (!$post) {
    header('Location: home.php');
    exit;
}

// Get previous post
$prev_sql = "SELECT p.*, c.name as category_name 
             FROM posts p 
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = 'published' 
             AND p.deleted_at IS NULL 
             AND p.is_active = 1
             AND c.deleted_at IS NULL 
             AND c.is_active = 1
             AND (
                 p.created_at < ? 
                 OR (p.created_at = ? AND p.id < ?)
             )
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT 1";
$stmt = $conn->prepare($prev_sql);
$stmt->bind_param("ssi", $post['created_at'], $post['created_at'], $post['id']);
$stmt->execute();
$prev_post = $stmt->get_result()->fetch_assoc();

// Get next post
$next_sql = "SELECT p.*, c.name as category_name 
             FROM posts p 
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.status = 'published' 
             AND p.deleted_at IS NULL 
             AND p.is_active = 1
             AND c.deleted_at IS NULL 
             AND c.is_active = 1
             AND (
                 p.created_at > ? 
                 OR (p.created_at = ? AND p.id > ?)
             )
             ORDER BY p.created_at ASC, p.id ASC
             LIMIT 1";
$stmt = $conn->prepare($next_sql);
$stmt->bind_param("ssi", $post['created_at'], $post['created_at'], $post['id']);
$stmt->execute();
$next_post = $stmt->get_result()->fetch_assoc();

// Check if current user is the author of the post
$isAuthor = isLoggedIn() && $_SESSION['user_id'] == $post['author_id'];

// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment']) && (isAdmin() || $isAuthor)) {
    $comment_id = (int)$_POST['comment_id'];
    
    // Soft delete the comment and its replies
    $sql = "UPDATE comments SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? OR parent_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $comment_id, $comment_id);
    $stmt->execute();
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?slug=" . urlencode($slug) . "#comments");
    exit;
}

// Handle comment activation/deactivation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_comment']) && (isAdmin() || $isAuthor)) {
    $comment_id = (int)$_POST['comment_id'];
    $new_status = (int)$_POST['new_status'];
    
    // Toggle comment status
    $sql = "UPDATE comments SET is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $new_status, $comment_id);
    $stmt->execute();
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?slug=" . urlencode($slug) . "#comments");
    exit;
}

// Get visitor's IP address
$ip_address = $_SERVER['REMOTE_ADDR'];

// Check if this IP has viewed this post in the last hour
$sql = "SELECT id FROM post_views 
        WHERE post_id = ? 
        AND ip_address = ? 
        AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $post['id'], $ip_address);
$stmt->execute();
$result = $stmt->get_result();

// If no recent view from this IP, add view count
if ($result->num_rows === 0) {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert view record
        $sql = "INSERT INTO post_views (post_id, ip_address) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $post['id'], $ip_address);
        $stmt->execute();
        
        // Update post view count
        $sql = "UPDATE posts SET views = views + 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $post['id']);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
    } catch (Exception $e) {
        // If there's an error, rollback changes
        $conn->rollback();
    }
}

// Get approved comments only
$sql = "SELECT c.*, u.username, u.avatar 
        FROM comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.post_id = ? AND c.deleted_at IS NULL AND c.is_active = 1
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
                        <img src="assets/images/default-profile.png" alt="Default profile">
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
                                      class="d-inline">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $comment['is_active'] ? '0' : '1'; ?>">
                                    <button type="submit" 
                                            name="toggle_comment" 
                                            class="btn btn-sm btn-link <?php echo $comment['is_active'] ? 'text-warning' : 'text-success'; ?>">
                                        <?php echo $comment['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
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
        @media (min-width: 992px) {
            .blog-posts .col-lg-4 {
                padding-left: 80px;
            }
        }
        
        .post-share {
            position: relative;
        }
        .share-menu {
            min-width: 200px;
            padding: 10px 0;
            position: absolute;
            bottom: unset !important;
            top: -10px !important;
            left: -100px !important;
            transform: translateY(-100%) !important;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            z-index: 1050;
        }
        .share-menu .dropdown-item {
            padding: 8px 20px;
            color: #333;
            font-size: 14px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .share-menu .dropdown-item i {
            width: 25px;
            text-align: center;
            color: #333;
            transition: all 0.3s ease;
        }
        .share-menu .dropdown-item:hover,
        .share-menu .dropdown-item:focus,
        .share-menu .dropdown-item:active {
            background-color: #f48840;
            color: #fff !important;
            text-decoration: none;
        }
        .share-menu .dropdown-item:hover i,
        .share-menu .dropdown-item:focus i,
        .share-menu .dropdown-item:active i {
            color: #fff !important;
        }
        .share-menu .dropdown-divider {
            margin: 5px 0;
        }

        /* Mobile styles for share dropdown */
        @media (max-width: 767px) {
            .share-menu {
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                bottom: auto !important;
                right: auto !important;
                transform: translate(-50%, -50%) !important;
                width: 90% !important;
                max-width: 300px !important;
                margin: 0 !important;
                background: white !important;
                z-index: 1050 !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            }
            .share-menu .dropdown-item {
                padding: 12px 20px !important;
                font-size: 16px !important;
            }
            .share-menu .dropdown-item i {
                width: 30px !important;
                font-size: 18px !important;
            }
            .dropdown-backdrop {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                background-color: rgba(0,0,0,0.5);
                z-index: 1040;
            }
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
            color: #20232e;
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
        .post-content p {
            border: none !important;
            padding: 0 !important;
            margin-bottom: 1rem;
            color: #181818;
        }
        .post-content {
            color: #181818;
        }
        .post-content * {
            color: #181818;
        }
        .post-content p:after {
            display: none !important;
        }
        .post-info li a {
            color: #484848 !important;
        }
        .post-info li:after {
            color: #484848;
        }
        .comment-content p {
            color: #2b2b2b;
        }
        .comment-content {
            color: #2b2b2b;
        }
        .sidebar-item.comments .content p {
            color: #2b2b2b;
        }
        .replies-container p {
            color: #2b2b2b;
        }
        .sidebar-item p {
            color: #484848;
        }
        .text-muted {
            color: #666666 !important;
        }
        .down-content span {
            color: #484848;
        }
        .post-tags li a {
            color: #484848;
        }
        .sidebar-item .content p {
            color: #484848;
        }
        .comment-header h4 {
            color: #f48840;
        }
        .comment-header .text-muted {
            font-size: 12px;
        }
        .comment-header div h4 {
            margin-bottom: 2px;
        }
        .post-info li a:hover,
        .post-tags li a:hover {
            color: #f48840 !important;
        }
        /* Make horizontal lines black */
        .down-content {
            border-bottom: 1px solid #dddddd;
        }
        .post-options {
            border-top: 1px solid #dddddd;
        }
        .sidebar-item {
            border-bottom: 1px solid #dddddd;
        }
        .dropdown-divider {
            border-top: 1px solid #dddddd;
        }
        .comment-content {
            border: 1px solid #dddddd;
        }
        /* Share dropdown styles */
        .post-share .dropdown-toggle::after {
            display: none;
        }
        .post-share .nav-link {
            padding: 0;
            color: #353935;
        }
        .post-share li {
            color: #353935;
        }
        .post-tags li {
            color: #353935;
        }
        .post-tags li a {
            color: #353935 !important;
        }
        .post-share li a {
            color: #353935 !important;
        }
        /* Keep hover color */
        .post-share .nav-link:hover,
        .post-tags li a:hover,
        .post-share li a:hover {
            color: #f48840 !important;
            text-decoration: none;
        }
        .share-menu {
            min-width: 200px;
            padding: 10px 0;
            margin-top: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: absolute;
            bottom: 100%;
            right: 0;
            margin-bottom: 10px;
            background: white;
            border-radius: 4px;
            z-index: 1000;
        }

        .share-menu .dropdown-item {
            padding: 8px 20px;
            color: #333;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .share-menu .dropdown-item i {
            width: 25px;
            text-align: center;
            color: #333;
            transition: all 0.3s ease;
        }

        /* New responsive styles for share dropdown */
        @media (max-width: 767px) {
            .share-menu {
                position: fixed !important;
                top: 50% !important;
                left: 50% !important;
                bottom: auto !important;
                right: auto !important;
                transform: translate(-50%, -50%) !important;
                width: 90% !important;
                max-width: 300px !important;
                margin: 0 !important;
                background: white !important;
                z-index: 1050 !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            }
            .share-menu .dropdown-item {
                padding: 12px 20px !important;
                font-size: 16px !important;
            }
            .share-menu .dropdown-item i {
                width: 30px !important;
                font-size: 18px !important;
            }
            .dropdown-backdrop {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                background-color: rgba(0,0,0,0.5);
                z-index: 1040;
            }
        }
        /* Tag cloud colors */
        .tagcloud li a {
            color: #353935 !important;
        }
        .tagcloud li a:hover {
            color: #f48840 !important;
        }
        /* Category list colors in sidebar */
        .categories li a {
            color: #353935 !important;
        }
        .categories li a:hover {
            color: #f48840 !important;
        }
        /* Post count in categories and tags */
        .categories li span,
        .tagcloud li span {
            color: #353935;
        }

        .post-navigation {
            padding: 30px 0;
            margin: 30px 0;
            border-top: 1px solid #dddddd;
            border-bottom: 1px solid #dddddd;
        }

        .post-navigation .prev-post,
        .post-navigation .next-post {
            display: flex;
            flex-direction: column;
        }

        .post-navigation span {
            font-size: 14px;
            color: #181818;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .post-navigation a {
            color: #181818;
            font-weight: 500;
            font-size: 16px;
            text-decoration: none;
            transition: color 0.3s ease;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .post-navigation span:hover {
            color: #0047cc;
        }

        .post-navigation .next-post {
            align-items: flex-end;
            text-align: right;
        }

        .post-navigation .prev-post span i {
            margin-right: 5px;
        }

        .post-navigation .next-post span i {
            margin-left: 5px;
        }

        .share-menu .p-3 {
            padding: 1rem !important;
        }
        .share-menu .text-center {
            text-align: center !important;
        }
        .share-menu p {
            margin-bottom: 0.5rem !important;
            color: #333;
            font-size: 14px;
        }
        .share-menu .btn-primary {
            background-color: #f48840;
            border-color: #f48840;
            color: #fff;
            padding: 0.375rem 1.5rem;
            font-size: 14px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .share-menu .btn-primary:hover {
            background-color: #e67730;
            border-color: #e67730;
        }

        /* Post content readability improvements */
        .post-content {
            font-size: 18px;
            line-height: 1.8;
            color: #333333;
            max-width: 100%;
            margin: 30px 0;
        }

        .post-content p {
            margin-bottom: 1.5em;
            font-family: 'Roboto', sans-serif;
            font-weight: 400;
        }

        .post-content h1, 
        .post-content h2, 
        .post-content h3, 
        .post-content h4, 
        .post-content h5, 
        .post-content h6 {
            margin-top: 1.5em;
            margin-bottom: 0.8em;
            font-weight: 600;
            color: #1a1a1a;
            line-height: 1.4;
        }

        .post-content img {
            max-width: 100%;
            height: auto;
            margin: 2em 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .post-content blockquote {
            margin: 2em 0;
            padding: 1em 2em;
            border-left: 4px solid #f48840;
            background-color: #f9f9f9;
            font-style: italic;
            color: #555;
        }

        .post-content ul,
        .post-content ol {
            margin: 1.5em 0;
            padding-left: 2em;
        }

        .post-content li {
            margin-bottom: 0.5em;
            line-height: 1.6;
        }

        .post-content code {
            background-color: #f5f5f5;
            padding: 0.2em 0.4em;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.9em;
        }

        .post-content pre {
            background-color: #f5f5f5;
            padding: 1.5em;
            border-radius: 5px;
            overflow-x: auto;
            margin: 1.5em 0;
        }

        .blog-post .down-content {
            padding: 40px;
            background: #fff;
            border-radius: 0 0 8px 8px;
        }

        .blog-post .blog-thumb {
            margin-bottom: 0;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
        }

        .blog-post .blog-thumb img {
            width: 100%;
            height: auto;
            transition: transform 0.3s ease;
        }

        .blog-post .down-content span {
            font-size: 18px;
            font-weight: 500;
            color: #f48840;
            margin-bottom: 10px;
            display: inline-block;
        }

        .post-info {
            margin: 15px 0 25px 0;
        }

        .post-info li {
            font-size: 16px;
        }

        .post-info li a {
            font-weight: 500;
        }

        /* Improve readability on mobile */
        @media (max-width: 768px) {
            .post-content {
                font-size: 16px;
                line-height: 1.7;
            }

            .blog-post .down-content {
                padding: 25px;
            }

            .post-content blockquote {
                padding: 1em;
                margin: 1.5em 0;
            }
        }

        /* Add smooth transitions */
        .blog-post {
            transition: all 0.3s ease;
        }

        .blog-post:hover .blog-thumb img {
            transform: scale(1.02);
        }

        /* Improve code block readability */
        .post-content pre code {
            display: block;
            line-height: 1.6;
            tab-size: 4;
        }

        /* Add style for links within post content */
        .post-content a {
            color: #f48840;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.3s ease;
        }

        .post-content a:hover {
            border-bottom-color: #f48840;
        }

        /* Add style for tables if present in post content */
        .post-content table {
            width: 100%;
            margin: 2em 0;
            border-collapse: collapse;
        }

        .post-content th,
        .post-content td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .post-content th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        .post-content tr:nth-child(even) {
            background-color: #f9f9f9;
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
        <section class="page-heading" <?php if (!empty($post['image_path'])): ?> style="background-image: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('<?php echo htmlspecialchars($post['image_path']); ?>'); background-size: cover; background-position: center;" <?php endif; ?>>
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-content" style="color: #fff;">
                            <h4><?php echo htmlspecialchars($post['category_name']); ?></h4>
                            <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <section class="blog-posts grid-system">
        <div class="container" style="max-width: 1700px;">
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
                                                                WHERE pt.post_id = ?
                                                                AND t.deleted_at IS NULL 
                                                                AND t.is_active = 1";
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
                                                        <li class="nav-item dropdown">
                                                            <a href="#" class="nav-link" id="shareDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                Share
                                                            </a>
                                                            <div class="dropdown-menu dropdown-menu-left share-menu" aria-labelledby="shareDropdown">
                                                                <?php
                                                                $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
                                                                $title = urlencode($post['title']);
                                                                $url = urlencode($current_url);
                                                                $return_url = urlencode($_SERVER['REQUEST_URI']);
                                                                
                                                                if (isLoggedIn()):
                                                                ?>
                                                                <a class="dropdown-item" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $url; ?>" target="_blank" rel="noopener noreferrer">
                                                                    <i class="fa fa-facebook"></i> Facebook
                                                                </a>
                                                                <a class="dropdown-item" href="https://twitter.com/intent/tweet?url=<?php echo $url; ?>&text=<?php echo $title; ?>" target="_blank" rel="noopener noreferrer">
                                                                    <i class="fa fa-twitter"></i> Twitter
                                                                </a>
                                                                <a class="dropdown-item" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $url; ?>&title=<?php echo $title; ?>" target="_blank" rel="noopener noreferrer">
                                                                    <i class="fa fa-linkedin"></i> LinkedIn
                                                                </a>
                                                                <a class="dropdown-item" href="https://api.whatsapp.com/send?text=<?php echo $title . ' ' . $url; ?>" target="_blank" rel="noopener noreferrer">
                                                                    <i class="fa fa-whatsapp"></i> WhatsApp
                                                                </a>
                                                                <div class="dropdown-divider"></div>
                                                                <a class="dropdown-item copy-link" href="#" data-url="<?php echo htmlspecialchars($current_url); ?>">
                                                                    <i class="fa fa-link"></i> Copy Link
                                                                </a>
                                                                <?php else: ?>
                                                                <div class="p-3 text-center">
                                                                    <p class="mb-2">Please login to share this post</p>
                                                                    <a href="login.php?return_url=<?php echo $return_url; ?>" class="btn btn-primary btn-sm">Login</a>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Post Navigation -->
                            <div class="col-lg-12">
                                <div class="post-navigation">
                                    <div class="row">
                                        <div class="col-6">
                                            <?php if ($prev_post): ?>
                                            <div class="prev-post">
                                                <a href="post-details.php?slug=<?php echo urlencode($prev_post['slug']); ?>">
                                                    <span><i class="fa fa-arrow-left"></i> Previous Post</span>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-6 text-right">
                                            <?php if ($next_post): ?>
                                            <div class="next-post">
                                                <a href="post-details.php?slug=<?php echo urlencode($next_post['slug']); ?>">
                                                    <span>Next Post <i class="fa fa-arrow-right"></i></span>
                                                </a>
                                            </div>
                                            <?php endif; ?>
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
                                            <p><a href="login.php?return_url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Login</a> to leave a comment.</p>
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
            // Initialize dropdowns
            $('.dropdown-toggle').dropdown();

            // Handle copy link functionality
            $('.copy-link').on('click', function(e) {
                e.preventDefault();
                <?php if (!isLoggedIn()): ?>
                    window.location.href = 'login.php?return_url=<?php echo $return_url; ?>';
                <?php else: ?>
                    var url = $(this).data('url');
                    navigator.clipboard.writeText(url).then(function() {
                        $('.copy-success').fadeIn().delay(2000).fadeOut();
                    }).catch(function() {
                        // Fallback for older browsers
                        var $temp = $("<input>");
                        $("body").append($temp);
                        $temp.val(url).select();
                        document.execCommand("copy");
                        $temp.remove();
                        $('.copy-success').fadeIn().delay(2000).fadeOut();
                    });
                <?php endif; ?>
            });

            // Handle share menu positioning
            $('.post-share .nav-link').on('click', function(e) {
                e.preventDefault();
                var $dropdownMenu = $(this).siblings('.share-menu');
                
                if ($dropdownMenu.is(':visible')) {
                    $dropdownMenu.hide();
                    $('.dropdown-backdrop').remove();
                } else {
                    $dropdownMenu.show();
                    if ($(window).width() <= 767) {
                        $('<div class="dropdown-backdrop"></div>').insertAfter('.share-menu');
                    }
                }
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.post-share').length) {
                    $('.share-menu').hide();
                    $('.dropdown-backdrop').remove();
                }
            });

            // Prevent dropdown from closing when clicking inside
            $('.share-menu').on('click', function(e) {
                e.stopPropagation();
            });

            // Close share menu when clicking backdrop on mobile
            $(document).on('click', '.dropdown-backdrop', function() {
                $('.share-menu').hide();
                $('.dropdown-backdrop').remove();
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