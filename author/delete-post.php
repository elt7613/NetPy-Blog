<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is author
if (!isLoggedIn() || $_SESSION['role'] !== 'author') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get post ID and action
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate post ID
if ($post_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

// Check if the post belongs to the current author
$check_sql = "SELECT id FROM posts WHERE id = ? AND author_id = ? AND deleted_at IS NULL";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'You do not have permission to modify this post']);
    exit();
}

// Handle the action
if ($action === 'toggle') {
    // Toggle post status
    $sql = "UPDATE posts SET is_active = 1 - is_active WHERE id = ? AND author_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to update post status']);
    }
} elseif ($action === 'delete') {
    // Soft delete the post
    $sql = "UPDATE posts SET deleted_at = NOW() WHERE id = ? AND author_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Failed to delete post']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid action']);
}

exit(); 