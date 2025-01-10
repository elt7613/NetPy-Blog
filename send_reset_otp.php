<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'email.php';
require_once 'functions.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $email = sanitizeInput($_POST['email']);

    if (empty($email)) {
        throw new Exception('Email is required');
    }

    // Check if email exists
    $sql = "SELECT id FROM users WHERE email = ?";
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
        throw new Exception('Email not found');
    }

    // Generate OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Store OTP in database
    $sql = "INSERT INTO password_resets (email, otp, expiry) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE otp = ?, expiry = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("sssss", $email, $otp, $expiry, $otp, $expiry);
    if (!$stmt->execute()) {
        throw new Exception('Failed to store OTP: ' . $stmt->error);
    }

    // Send email with OTP
    $subject = "Password Reset Verification Code";
    $htmlBody = "
    <html>
    <body>
        <h2>Password Reset Request</h2>
        <p>You have requested to reset your password. Here is your verification code:</p>
        <h1 style='font-size: 24px; letter-spacing: 2px; color: #f48840;'>{$otp}</h1>
        <p>This code will expire in 10 minutes.</p>
        <p>If you did not request this password reset, please ignore this email.</p>
    </body>
    </html>";

    $plainTextBody = "Your password reset verification code is: {$otp}\nThis code will expire in 10 minutes.\nIf you did not request this password reset, please ignore this email.";

    $emailResult = sendEmail($email, "", $subject, $htmlBody, $plainTextBody);
    
    if (strpos($emailResult, 'success') === false) {
        error_log("Email sending failed: " . $emailResult);
        throw new Exception('Failed to send verification code');
    }

    die(json_encode(['success' => true, 'message' => 'Verification code sent to your email']));

} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
} 