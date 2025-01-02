<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    $post_id = (int)$_POST['post_id'];
    
    // Verify the post belongs to this author
    $stmt = $conn->prepare("SELECT image_path, author_id FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    
    if ($post && $post['author_id'] == $_SESSION['user_id']) {
        // Delete post tags first (foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM post_tags WHERE post_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        
        // Delete comments associated with the post
        $stmt = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        
        // Delete the post
        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? AND author_id = ?");
        $stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            // Delete the post image if it exists
            if ($post['image_path'] && file_exists('../' . $post['image_path'])) {
                unlink('../' . $post['image_path']);
            }
            $_SESSION['success_msg'] = "Post deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Error deleting post.";
        }
    } else {
        $_SESSION['error_msg'] = "You can only delete your own posts.";
    }
}

header('Location: dashboard.php');
exit(); 