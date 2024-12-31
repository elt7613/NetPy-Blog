<?php
require_once 'config.php';

try {
    $sql = "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "Avatar column added successfully";
    } else {
        echo "Error adding avatar column: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 