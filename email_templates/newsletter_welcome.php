<?php
function getNewsletterWelcomeTemplate($email) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #4A90E2; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 10px 20px; background: #4A90E2; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Welcome to NetPy Blog Newsletter!</h1>
            </div>
            <div class="content">
                <p>Dear Subscriber,</p>
                <p>Thank you for subscribing to our newsletter! We\'re excited to have you join our community.</p>
                <p>You\'ll now receive regular updates about:</p>
                <ul>
                    <li>Latest blog posts and articles</li>
                    <li>Tech insights and tutorials</li>
                    <li>Programming tips and best practices</li>
                    <li>Special announcements and features</li>
                </ul>
                <p>Visit our blog to start exploring our content:</p>
                <p style="text-align: center;">
                    <a href="' . SITE_URL . '" class="button">Visit Our Blog</a>
                </p>
                <p>If you have any questions or feedback, feel free to reply to this email.</p>
                <p>Best regards,<br>The NetPy Blog Team</p>
            </div>
            <div class="footer">
                <p>You received this email because you subscribed to NetPy Blog Newsletter with the email: ' . $email . '</p>
            </div>
        </div>
    </body>
    </html>';
}
?> 