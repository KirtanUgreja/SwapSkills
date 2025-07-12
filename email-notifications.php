
<?php
// email-notifications.php - Complete Email Notifications System

// Check if PHPMailer is available
if (!file_exists('vendor/autoload.php')) {
    // If PHPMailer is not available, create dummy functions
    function sendChatNotification($email, $senderName, $receiverName) {
        error_log("Email notification would be sent to: $email from: $senderName");
        return true;
    }
    
    function sendAdminAlert($email, $title, $message) {
        error_log("Admin alert would be sent to: $email - $title");
        return true;
    }
    
    return;
}

require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Email Configuration Class
class EmailConfig {
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USERNAME = 'skillswapodoo@gmail.com';
    const SMTP_PASSWORD = 'bfyj ichs bqbp vkkz';
    const FROM_EMAIL = 'skillswapodoo@gmail.com';
    const FROM_NAME = 'SwapSkills';
    const ENCRYPTION = PHPMailer::ENCRYPTION_STARTTLS;
}

/**
 * Create and configure PHPMailer instance
 * @return PHPMailer|false
 */
function createMailer() {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = EmailConfig::SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EmailConfig::SMTP_USERNAME;
        $mail->Password = EmailConfig::SMTP_PASSWORD;
        $mail->SMTPSecure = EmailConfig::ENCRYPTION;
        $mail->Port = EmailConfig::SMTP_PORT;
        
        // Sender info
        $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        return $mail;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification
 * @param string $email Recipient email
 * @param string $subject Email subject
 * @param string $body HTML body
 * @param string $altBody Plain text body
 * @return bool Success status
 */
function sendEmailNotification($email, $subject, $body, $altBody = '') {
    $mail = createMailer();
    if (!$mail) return false;
    
    try {
        $mail->addAddress($email);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send chat notification
 * @param string $email Receiver email
 * @param string $senderName Sender's name
 * @param string $receiverName Receiver's name
 * @return bool Success status
 */
function sendChatNotification($email, $senderName, $receiverName) {
    $subject = 'New Message - SwapSkills';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .cta-button { 
                display: inline-block; 
                background-color: #007bff; 
                color: white; 
                padding: 12px 24px; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 15px 0;
            }
            .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Message on SwapSkills</h2>
            </div>
            <div class='content'>
                <p>Hello {$receiverName},</p>
                <p>You have received a new message from <strong>{$senderName}</strong> on SwapSkills.</p>
                <p>Log in to your account to read and reply to this message.</p>
                <a href='#' class='cta-button'>View Messages</a>
                <p>Best regards,<br>The SwapSkills Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from SwapSkills</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $altBody = "Hello {$receiverName},\n\nYou have received a new message from {$senderName} on SwapSkills.\n\nLog in to your account to read and reply to this message.\n\nBest regards,\nThe SwapSkills Team";

    return sendEmailNotification($email, $subject, $body, $altBody);
}

/**
 * Send admin alert
 * @param string $email Recipient email
 * @param string $title Alert title
 * @param string $message Alert message
 * @return bool Success status
 */
function sendAdminAlert($email, $title, $message) {
    $subject = $title . ' - SwapSkills';

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #ff6b35; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$title}</h2>
            </div>
            <div class='content'>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <p>Best regards,<br>The SwapSkills Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from SwapSkills</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $altBody = $message . "\n\nBest regards,\nThe SwapSkills Team";

    return sendEmailNotification($email, $subject, $body, $altBody);
}
?>
