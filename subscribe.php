<?php
require_once 'config.php';
require_once 'email.php';
require_once 'email_templates/newsletter_welcome.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
        exit;
    }

    // Check if email already exists and is active
    $check_query = "SELECT * FROM netpy_newsletter_users WHERE email = ? AND is_active = 1 AND deleted_at IS NULL";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['status' => 'info', 'message' => 'You are already subscribed to our newsletter!']);
    } else {
        // Insert new subscription
        $insert_query = "INSERT INTO netpy_newsletter_users (email) VALUES (?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("s", $email);
        
        if ($stmt->execute()) {
            // Send welcome email
            $subject = "Welcome to NetPy Blog Newsletter!";
            $emailTemplate = getNewsletterWelcomeTemplate($email);
            
            if(sendEmail($email, "Subscriber", $subject, $emailTemplate)) {
                echo json_encode(['status' => 'success', 'message' => 'Thank you for subscribing to our newsletter! Please check your email for a welcome message.']);
            } else {
                // Still consider it a success even if email fails, but don't mention the welcome email
                echo json_encode(['status' => 'success', 'message' => 'Thank you for subscribing to our newsletter!']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Something went wrong. Please try again later.']);
        }
    }
    
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}

$conn->close();
?> 