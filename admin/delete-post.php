<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)$_POST['post_id'];
    
    if (isset($_POST['toggle_status'])) {
        // Toggle post active status
        $new_status = (int)$_POST['new_status'];
        $stmt = $conn->prepare("UPDATE posts SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $post_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = $new_status ? "Post activated successfully!" : "Post deactivated successfully!";
        } else {
            $_SESSION['error_msg'] = "Error updating post status.";
        }
    } else if (isset($_POST['delete_post'])) {
        // Soft delete the post
        $stmt = $conn->prepare("UPDATE posts SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $post_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Post soft deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Error deleting post.";
        }
    }
}

header('Location: dashboard.php');
exit(); 