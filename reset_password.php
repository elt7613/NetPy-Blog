<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        throw new Exception('Email and password are required');
    }

    // Validate password length
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters long');
    }

    // Verify that there is a valid OTP verification for this email
    $sql = "SELECT * FROM password_resets WHERE email = ? AND used = 1 AND expiry > NOW() ORDER BY expiry DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Invalid password reset request. Please start the process again.');
    }

    // Update password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE users SET password = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("ss", $hashed_password, $email);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update password. Please try again.');
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to update password. User not found.');
    }
    
    // Clear all password reset entries for this email
    $sql = "DELETE FROM password_resets WHERE email = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute(); // We don't need to check this result as it's cleanup
    
    die(json_encode(['success' => true, 'message' => 'Password reset successful']));

} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}
?> 