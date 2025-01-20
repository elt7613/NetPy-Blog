<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a valid email address.'
        ]);
        exit;
    }

    // Check if email already exists and is active
    $check_sql = "SELECT * FROM netpy_newsletter_users 
                  WHERE email = ? 
                  AND is_active = 1 
                  AND deleted_at IS NULL";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode([
            'status' => 'info',
            'message' => 'You are already subscribed to our newsletter!'
        ]);
        exit;
    }

    // Insert new subscriber
    $insert_sql = "INSERT INTO netpy_newsletter_users (email) VALUES (?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("s", $email);

    if ($stmt->execute()) {
        // Send welcome email
        $to = $email;
        $subject = "Welcome to NetPy Newsletter!";
        
        $message = "
        <html>
        <head>
            <title>Welcome to NetPy Newsletter</title>
        </head>
        <body>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #0d47a1;'>Welcome to NetPy Newsletter!</h2>
                <p>Thank you for subscribing to our newsletter. You'll now receive updates about:</p>
                <ul>
                    <li>Latest blog posts</li>
                    <li>Tech tutorials</li>
                    <li>Programming insights</li>
                    <li>Development best practices</li>
                </ul>
                <p>Stay tuned for our upcoming content!</p>
                <p>Best regards,<br>NetPy Team</p>
            </div>
        </body>
        </html>
        ";

        // Headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: NetPy Technologies <noreply@netpy.tech>' . "\r\n";

        // Send email
        mail($to, $subject, $message, $headers);

        echo json_encode([
            'status' => 'success',
            'message' => 'Thank you for subscribing to our newsletter!'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'An error occurred. Please try again later.'
        ]);
    }

    $stmt->close();
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
}

$conn->close();
?> 