<?php
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    expiry DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX email_index (email),
    INDEX otp_index (otp)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table password_resets created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close(); 