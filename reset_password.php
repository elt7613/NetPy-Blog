<?php
require_once 'config.php';

$username = 'admin';
$password = 'Password';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = ? WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $hashed_password, $username);

if ($stmt->execute()) {
    echo "Password reset successful!";
} else {
    echo "Error resetting password: " . $conn->error;
}
?> 