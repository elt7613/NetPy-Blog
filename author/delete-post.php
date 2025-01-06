<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)$_POST['post_id'];
    
    // Verify the post belongs to this author
    $stmt = $conn->prepare("SELECT author_id, is_active FROM posts WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    
    if ($post && $post['author_id'] == $_SESSION['user_id']) {
        if (isset($_POST['toggle_status'])) {
            // Toggle post active status
            $new_status = (int)$_POST['new_status'];
            $stmt = $conn->prepare("UPDATE posts SET is_active = ? WHERE id = ? AND author_id = ?");
            $stmt->bind_param("iii", $new_status, $post_id, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = $new_status ? "Post activated successfully!" : "Post deactivated successfully!";
            } else {
                $_SESSION['error_msg'] = "Error updating post status.";
            }
        } else if (isset($_POST['delete_post'])) {
            // Soft delete the post
            $stmt = $conn->prepare("UPDATE posts SET deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND author_id = ?");
            $stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Post soft deleted successfully!";
            } else {
                $_SESSION['error_msg'] = "Error deleting post.";
            }
        }
    } else {
        $_SESSION['error_msg'] = "You can only modify your own active posts.";
    }
}

header('Location: dashboard.php');
exit(); 