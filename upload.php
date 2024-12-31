<?php
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in and is admin or author
if (!isLoggedIn() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'author')) {
    die(json_encode(['error' => 'Unauthorized']));
}

if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'assets/images/editor/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        die(json_encode(['error' => 'Invalid file type']));
    }
    
    $new_filename = uniqid() . '.' . $file_extension;
    $target_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
        // Return the location of the uploaded file
        echo json_encode(['location' => $target_path]);
    } else {
        echo json_encode(['error' => 'Failed to upload file']);
    }
} else {
    echo json_encode(['error' => 'No file uploaded']);
} 