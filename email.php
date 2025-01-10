<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer library
require 'vendor/autoload.php';

function sendEmail($toEmail, $toName, $subject, $htmlBody, $plainTextBody = '') {
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        // Server settings
        $mail->SMTPDebug = 0;  // Enable verbose debug output if needed
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'djangochatbox@gmail.com';
        $mail->Password   = 'mbmk cavq qzpv gqai';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('djangochatbox@gmail.com', 'NetPy');
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainTextBody ?: strip_tags($htmlBody);

        // Send the email
        if (!$mail->send()) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return "Failed to send email: " . $mail->ErrorInfo;
        }
        
        return 'Email sent successfully!';
        
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return "Email error: " . $e->getMessage();
    }
}
