<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

function sendVerificationEmail($email, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'chefaicatering09@gmail.com'; // Your Gmail
        $mail->Password = 'ceuwbzqizxiqkovz'; // Remove dashes from app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Additional SMTP options for better compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients - FROM should match the username
        $mail->setFrom('chefaicatering09@gmail.com', 'ChefAI');
        $mail->addAddress($email);
        $mail->addReplyTo('chefaicatering09@gmail.com', 'ChefAI Support');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - ChefAI';
        
        $resetLink = "https://chefaiph.site/forgot-password.php?token=" . $token;
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #C9B194;'>Password Reset Request</h2>
                <p>You requested to reset your password for ChefAI.</p>
                <p>Click the button below to reset your password:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink' style='background: #C9B194; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;'>Reset Password</a>
                </div>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
                <hr>
                <p style='color: #666; font-size: 12px;'>This is an automated message from ChefAI.</p>
            </div>
        ";
        
        $mail->AltBody = "Password Reset Link: $resetLink\n\nThis link will expire in 1 hour.";
        
        $mail->send();
        return ['success' => true, 'error' => ''];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>