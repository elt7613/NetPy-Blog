<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// TinyMCE API Key
define('TINYMCE_API_KEY', 'nz9stqlza3ji0i4oj5u6g3nbbvsizqxd2wrwyv5cn120k2y3');

try {
    $servername = "127.0.0.1";
    $username = "elt";
    $password = "Password";
    $dbname = "netpy_blog";

    // Create connection
    $conn = mysqli_connect($servername, $username, $password, $dbname);

    // Check connection
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    //echo "Connected successfully"; 
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

date_default_timezone_set("Asia/Kolkata");
session_start();
?>
