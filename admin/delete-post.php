<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    $new_status = isset($_POST['new_status']) ? (int)$_POST['new_status'] : 0;
    
    if ($post_id > 0) {
        // Update post active status
        $stmt = $conn->prepare("UPDATE posts SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $new_status, $post_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = $new_status ? "Post activated successfully!" : "Post deactivated successfully!";
        } else {
            $_SESSION['error_msg'] = "Error updating post status.";
        }
        $stmt->close();
    } else {
        $_SESSION['error_msg'] = "Invalid post ID";
    }
}

// Redirect back to dashboard
header('Location: dashboard.php');
exit(); 