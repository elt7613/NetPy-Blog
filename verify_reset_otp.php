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
    $otp = sanitizeInput($_POST['otp']);

    if (empty($email) || empty($otp)) {
        throw new Exception('Email and OTP are required');
    }

    // Verify OTP
    $sql = "SELECT * FROM password_resets 
            WHERE email = ? 
            AND otp = ? 
            AND expiry > NOW() 
            AND used = 0 
            ORDER BY expiry DESC 
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("ss", $email, $otp);
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        // Check if OTP exists but is expired
        $sql = "SELECT expiry FROM password_resets WHERE email = ? AND otp = ? AND used = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $expiredResult = $stmt->get_result();
        
        if ($expiredResult->num_rows > 0) {
            $row = $expiredResult->fetch_assoc();
            if (strtotime($row['expiry']) < time()) {
                throw new Exception('Verification code has expired. Please request a new one.');
            }
        }
        
        throw new Exception('Invalid verification code. Please try again.');
    }

    // Mark OTP as used
    $sql = "UPDATE password_resets SET used = 1 WHERE email = ? AND otp = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("ss", $email, $otp);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update OTP status: ' . $stmt->error);
    }

    die(json_encode(['success' => true, 'message' => 'Verification code verified successfully']));

} catch (Exception $e) {
    error_log("OTP verification error: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
} 